<?php

declare(strict_types=1);

namespace LibreNMS\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ConvertDatastorePutCalls extends AbstractRector
{
    private array $variableAssignments = [];
    private array $rrdDatasets = []; // Store RRD dataset definitions
    private array $variableUsages = []; // Track which variables are used

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Datastore put() method calls to write() method calls with proper FieldValue handling',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$datastore->put($os->getDeviceArray(), 'xdsl2LineStatusActAtp', [
    'ifName' => $ifName,
    'rrd_name' => ['xdsl2LineStatusActAtp', $ifName],
    'rrd_def' => RrdDefinition::make()
        ->addDataset('ds', 'GAUGE', -100)
        ->addDataset('us', 'GAUGE', -100),
], [
    'ds' => $data['xdsl2LineStatusActAtpDs'] ?? null,
    'us' => $data['xdsl2LineStatusActAtpUs'] ?? null,
]);
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
$datastore->write('xdsl2LineStatusActAtp', [
    'ds' => FieldValue::asInt($data['xdsl2LineStatusActAtpDs'] ?? null, StorageType::GAUGE)->min(-100),
    'us' => FieldValue::asInt($data['xdsl2LineStatusActAtpUs'] ?? null, StorageType::GAUGE)->min(-100),
], [
    'ifName' => $ifName,
]);
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Stmt::class];
    }

    public function refactor(Node $node): ?Node
    {
        // Reset state at the beginning of each file processing
        // We detect this by checking if we're processing a new file context
        static $lastFileHash = null;
        $currentFileHash = spl_object_hash($this->file ?? new \stdClass());

        if ($lastFileHash !== $currentFileHash) {
            $this->variableAssignments = [];
            $this->rrdDatasets = [];
            $this->variableUsages = [];
            $lastFileHash = $currentFileHash;
        }

        // Collect variable assignments first
        if ($node instanceof Expression && $node->expr instanceof Assign) {
            $this->collectVariableAssignment($node->expr);
            return null;
        }

        // Process put method calls
        if ($node instanceof Expression && $node->expr instanceof MethodCall) {
            $methodCall = $node->expr;

            if (!$this->isName($methodCall->name, 'put')) {
                return null;
            }

            if (!$this->isDatastoreInstance($methodCall->var)) {
                return null;
            }

            if (count($methodCall->args) !== 4) {
                return null;
            }

            return $this->transformPutToWrite($node, $methodCall);
        }

        return null;
    }



    private function collectVariableAssignment(Assign $assign): void
    {
        if ($assign->var instanceof Variable) {
            $varName = $this->getName($assign->var);
            if ($varName) {
                $this->variableAssignments[$varName] = $assign->expr;
            }
        }
    }

    private function transformPutToWrite(Expression $node, MethodCall $methodCall): ?Expression
    {
        $deviceArg = $methodCall->args[0]->value;
        $measurementArg = $methodCall->args[1]->value;
        $tagsArg = $methodCall->args[2]->value;
        $fieldsArg = $methodCall->args[3]->value;

        // Track usage of variables in the arguments
        $this->trackVariableUsage($tagsArg);
        $this->trackVariableUsage($fieldsArg);

        // Resolve variables to their actual values
        $tagsArray = $this->resolveToArray($tagsArg);
        $fieldsArray = $this->resolveToArray($fieldsArg);

        // If we can't resolve the fields array, don't transform to avoid corruption
        if (empty($fieldsArray->items)) {
            return null;
        }

        // Extract RRD definition and parse datasets
        $this->extractRrdDefinition($tagsArray);

        // Extract metadata tags (non-RRD tags) and rrd_name tags
        $metadataTags = $this->extractMetadataTags($tagsArray);

        // Convert fields to FieldValue calls with RRD dataset info
        $convertedFields = $this->convertFieldsToFieldValues($fieldsArray);

        // If conversion failed, don't transform
        if (empty($convertedFields->items)) {
            return null;
        }

        // Build new method call arguments
        $writeArgs = [
            new Node\Arg($measurementArg),
            new Node\Arg($convertedFields)
        ];

        // Add metadata tags if present
        if (!empty($metadataTags->items)) {
            $writeArgs[] = new Node\Arg($metadataTags);
        }

        $newMethodCall = new MethodCall(
            $methodCall->var,
            'write',
            $writeArgs
        );

        return new Expression($newMethodCall);
    }

    private function trackVariableUsage(Node $node): void
    {
        if ($node instanceof Variable) {
            $varName = $this->getName($node);
            if ($varName) {
                $this->variableUsages[$varName] = true;
            }
        }

        // Recursively track usage in sub-nodes
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->$subNodeName;
            if ($subNode instanceof Node) {
                $this->trackVariableUsage($subNode);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $arrayItem) {
                    if ($arrayItem instanceof Node) {
                        $this->trackVariableUsage($arrayItem);
                    }
                }
            }
        }
    }

    private function extractRrdDefinition(Array_ $tags): void
    {
        $this->rrdDatasets = []; // Reset for each transformation

        foreach ($tags->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key) {
                continue;
            }

            $keyName = null;
            if ($item->key instanceof String_) {
                $keyName = $item->key->value;
            }

            if ($keyName === 'rrd_def') {
                $this->parseRrdDefinition($item->value);
                break;
            }
        }
    }

    private function parseRrdDefinition(Node $rrdDefNode): void
    {
        // Handle chained method calls like RrdDefinition::make()->addDataset(...)
        if ($rrdDefNode instanceof MethodCall) {
            $this->parseRrdDefinitionMethodCalls($rrdDefNode);
        }
        // Handle variable references
        elseif ($rrdDefNode instanceof Variable) {
            $varName = $this->getName($rrdDefNode);
            if ($varName && isset($this->variableAssignments[$varName])) {
                $this->parseRrdDefinition($this->variableAssignments[$varName]);
            }
        }
    }

    private function parseRrdDefinitionMethodCalls(MethodCall $methodCall): void
    {
        // Recursively parse the chain
        if ($methodCall->var instanceof MethodCall) {
            $this->parseRrdDefinitionMethodCalls($methodCall->var);
        }

        // Check if this is an addDataset call
        if ($this->isName($methodCall->name, 'addDataset')) {
            $this->parseAddDatasetCall($methodCall);
        }
    }

    private function parseAddDatasetCall(MethodCall $methodCall): void
    {
        if (count($methodCall->args) < 2) {
            return;
        }

        $nameArg = $methodCall->args[0]->value ?? null;
        $typeArg = $methodCall->args[1]->value ?? null;
        $minArg = $methodCall->args[2]->value ?? null;
        $maxArg = $methodCall->args[3]->value ?? null;

        if (!$nameArg instanceof String_ || !$typeArg instanceof String_) {
            return;
        }

        $datasetName = $nameArg->value;
        $storageType = $typeArg->value;

        // Parse min value - preserve null if not specified
        $minValue = null;
        if ($minArg !== null) {
            if ($minArg instanceof Node\Scalar\LNumber) {
                $minValue = $minArg->value;
            } elseif ($minArg instanceof Node\Expr\UnaryMinus && $minArg->expr instanceof Node\Scalar\LNumber) {
                $minValue = -$minArg->expr->value;
            }
        }

        // Parse max value
        $maxValue = null;
        if ($maxArg instanceof Node\Scalar\LNumber) {
            $maxValue = $maxArg->value;
        }

        $this->rrdDatasets[$datasetName] = [
            'type' => $storageType,
            'min' => $minValue,
            'max' => $maxValue,
            'has_min' => $minArg !== null, // Track if min was explicitly set
        ];
    }

    private function isDatastoreInstance(Node $var): bool
    {
        // Check for app('Datastore') pattern
        if ($var instanceof FuncCall && $this->isName($var->name, 'app')) {
            if (isset($var->args[0]) && $var->args[0]->value instanceof String_) {
                return $var->args[0]->value->value === 'Datastore';
            }
        }

        // Check for $datastore variable
        if ($var instanceof Variable && $this->isName($var, 'datastore')) {
            return true;
        }

        return false;
    }

    private function resolveToArray(Node $node): Array_
    {
        // If it's already an array, return it
        if ($node instanceof Array_) {
            return $node;
        }

        // If it's a variable, resolve it from our collected assignments
        if ($node instanceof Variable) {
            $varName = $this->getName($node);
            if ($varName && isset($this->variableAssignments[$varName])) {
                $assignment = $this->variableAssignments[$varName];
                if ($assignment instanceof Array_) {
                    return $assignment;
                }
                // Handle case where assignment might be another variable
                if ($assignment instanceof Variable) {
                    return $this->resolveToArray($assignment);
                }
            }
        }

        // Return empty array if we can't resolve it
        return new Array_();
    }

    private function extractMetadataTags(Array_ $tags): Array_
    {
        $metadataItems = [];
        $rrdNameTags = [];
        $existingTagValues = []; // Track existing tag values to prevent duplicates

        // First pass: collect regular metadata tags and track their values
        foreach ($tags->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key) {
                continue;
            }

            $keyName = null;
            if ($item->key instanceof String_) {
                $keyName = $item->key->value;
            }

            // Skip RRD-related tags but collect regular metadata
            if (in_array($keyName, ['rrd_def', 'rrd_name'], true)) {
                continue;
            }

            $metadataItems[] = $item;

            // Track the value to prevent duplicates
            $tagValue = $this->getNodeValue($item->value);
            if ($tagValue !== null) {
                $existingTagValues[] = $tagValue;
            }
        }

        // Second pass: extract tags from rrd_name (skip first element which is measurement)
        $rrdNameArray = null;
        foreach ($tags->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key) {
                continue;
            }

            $keyName = null;
            if ($item->key instanceof String_) {
                $keyName = $item->key->value;
            }

            if ($keyName === 'rrd_name') {
                $rrdNameArray = $this->resolveToArray($item->value);
                break;
            }
        }

        if ($rrdNameArray && !empty($rrdNameArray->items)) {
            $rrdNameItems = array_slice($rrdNameArray->items, 1); // Skip measurement
            foreach ($rrdNameItems as $index => $rrdNameItem) {
                if ($rrdNameItem instanceof ArrayItem && $rrdNameItem->value) {
                    $tagValue = $this->getNodeValue($rrdNameItem->value);

                    // Only add if this value doesn't already exist in the tags
                    if ($tagValue !== null && !in_array($tagValue, $existingTagValues, true)) {
                        $tagKey = "tag" . ($index + 1); // Create generic tag names
                        $rrdNameTags[] = new ArrayItem($rrdNameItem->value, new String_($tagKey));
                        $existingTagValues[] = $tagValue; // Track this value
                    }
                }
            }
        }

        // Combine regular metadata with non-duplicate rrd_name tags
        $allMetadataItems = array_merge($metadataItems, $rrdNameTags);

        return new Array_($allMetadataItems, [
            'kind' => Array_::KIND_SHORT
        ]);
    }

    private function getNodeValue(Node $node): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof Variable) {
            $varName = $this->getName($node);
            if ($varName) {
                return $varName; // Use variable name as identifier
            }
        }

        // For other node types, we can't easily determine the value
        return null;
    }

    private function convertFieldsToFieldValues(Array_ $fields): Array_
    {
        $convertedItems = [];

        foreach ($fields->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            $key = $item->key;
            $value = $item->value;

            // Skip invalid or unparseable values
            if (!$key || !$value) {
                continue;
            }

            // Skip if key is not a string or identifier
            if (!($key instanceof String_ || $key instanceof Node\Scalar\String_ || $key instanceof Variable)) {
                continue;
            }

            // Get the field name for RRD dataset lookup
            $fieldName = null;
            if ($key instanceof String_) {
                $fieldName = $key->value;
            }

            // Create appropriate FieldValue call with RRD dataset info
            try {
                $fieldValueCall = $this->createFieldValueCall($value, $fieldName);
                $arrayItem = new ArrayItem($fieldValueCall, $key);
                $arrayItem->setAttribute('comments', [new \PhpParser\Comment('/* field */')]); // do not remove this line!
                $convertedItems[] = $arrayItem;
            } catch (\Exception $e) {
                // If we can't parse the field value, skip it to avoid corruption
                continue;
            }
        }

        // Only create array if we have valid items
        if (empty($convertedItems)) {
            return new Array_([], [
                'kind' => Array_::KIND_SHORT
            ]);
        }

        return new Array_($convertedItems, [
            'kind' => Array_::KIND_SHORT
        ]);
    }

    private function createFieldValueCall(Node $value, ?string $fieldName): Node\Expr
    {
        // Get RRD dataset info if available
        $datasetInfo = null;
        if ($fieldName && isset($this->rrdDatasets[$fieldName])) {
            $datasetInfo = $this->rrdDatasets[$fieldName];
        }

        // Determine the base method (asInt or asFloat)
        $isFloat = $this->isFloatValue($value) || $this->isTimeValue($value);
        $method = $isFloat ? 'asFloat' : 'asInt';

        // Create storage type argument - default is GAUGE, so only include if different
        $storageTypeValue = 'GAUGE'; // Default is GAUGE
        $includeStorageType = false;

        if ($datasetInfo && isset($datasetInfo['type'])) {
            $storageTypeValue = strtoupper($datasetInfo['type']);
            $includeStorageType = ($storageTypeValue !== 'GAUGE');
        }

        // Build arguments for FieldValue call
        $args = [new Node\Arg($value)];

        // Only add storage type if it's not the default GAUGE
        if ($includeStorageType) {
            $storageTypeExpr = new Node\Expr\ClassConstFetch(
                new Name\FullyQualified(['LibreNMS', 'Data', 'Definitions', 'StorageType']),
                $storageTypeValue
            );
            $args[] = new Node\Arg($storageTypeExpr);
        }

        // Create base FieldValue call with fully qualified name
        $baseCall = new StaticCall(
            new Name\FullyQualified(['LibreNMS', 'Data', 'Definitions', 'FieldValue']),
            $method,
            $args
        );

        // Chain min() and max() calls if available
        $finalCall = $baseCall;

        if ($datasetInfo) {
            // Add min() call if min value was explicitly set in RRD definition
            if (isset($datasetInfo['has_min']) && $datasetInfo['has_min']) {
                $minValue = null;
                if ($datasetInfo['min'] === null) {
                    // Explicitly set to null to override default 0
                    $minValue = new Node\Expr\ConstFetch(new Name('null'));
                } elseif ($datasetInfo['min'] < 0) {
                    $minValue = new Node\Expr\UnaryMinus(new Node\Scalar\LNumber(abs($datasetInfo['min'])));
                } else {
                    $minValue = new Node\Scalar\LNumber($datasetInfo['min']);
                }
                $finalCall = new MethodCall($finalCall, 'min', [new Node\Arg($minValue)]);
            }

            // Add max() call if max value is set
            if (isset($datasetInfo['max']) && $datasetInfo['max'] !== null) {
                $maxValue = new Node\Scalar\LNumber($datasetInfo['max']);
                $finalCall = new MethodCall($finalCall, 'max', [new Node\Arg($maxValue)]);
            }
        }

        return $finalCall;
    }

    private function isTimeValue(Node $value): bool
    {
        if ($value instanceof Variable) {
            $name = $this->getName($value);
            return $name && (str_contains($name, 'time') || str_contains($name, 'Time'));
        }
        return false;
    }

    private function isFloatValue(Node $value): bool
    {
        // Check for division operations
        if ($value instanceof Node\Expr\BinaryOp\Div) {
            return true;
        }

        // Check for ternary with division
        if ($value instanceof Ternary) {
            if ($value->if instanceof Node\Expr\BinaryOp\Div) {
                return true;
            }
        }

        // Check for MOS field specifically (from your examples)
        if ($value instanceof Ternary && $this->containsDivisionBy100($value)) {
            return true;
        }

        return false;
    }

    private function containsDivisionBy100(Node $node): bool
    {
        if ($node instanceof Node\Expr\BinaryOp\Div) {
            if ($node->right instanceof Node\Scalar\LNumber && $node->right->value === 100) {
                return true;
            }
        }

        // Check child nodes recursively
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->$subNodeName;
            if ($subNode instanceof Node && $this->containsDivisionBy100($subNode)) {
                return true;
            }
            if (is_array($subNode)) {
                foreach ($subNode as $arrayItem) {
                    if ($arrayItem instanceof Node && $this->containsDivisionBy100($arrayItem)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a variable assignment should be removed because it's no longer used
     */
    private function shouldRemoveVariableAssignment(string $varName): bool
    {
        // Don't remove if the variable is used elsewhere
        return !isset($this->variableUsages[$varName]);
    }
}
