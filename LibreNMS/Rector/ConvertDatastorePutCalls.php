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

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Datastore put() method calls to write() method calls',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$tags = [
    'rrd_def' => RrdDefinition::make()->addDataset('time', 'GAUGE', 0),
];
$fields = [
    'time' => $agent_time,
];
app('Datastore')->put($device, 'agent', $tags, $fields);
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
app('Datastore')->write('agent', [
    'time' => FieldValue::asFloat($agent_time),
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

        // Resolve variables to their actual values
        $tagsArray = $this->resolveToArray($tagsArg);
        $fieldsArray = $this->resolveToArray($fieldsArg);

        // If we can't resolve the fields array, don't transform to avoid corruption
        if (empty($fieldsArray->items)) {
            return null;
        }

        // Extract metadata tags (non-RRD tags) and rrd_name tags
        $metadataTags = $this->extractMetadataTags($tagsArray);

        // Convert fields to FieldValue calls
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

            // Create appropriate FieldValue call, but handle parsing errors gracefully
            try {
                $fieldValueCall = $this->createFieldValueCall($value);
                // Remove the attributes that cause extra line returns
                $convertedItems[] = new ArrayItem(
                    $fieldValueCall,
                    $key
                );
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

    private function createFieldValueCall(Node $value): StaticCall
    {
        // Determine the type based on the value
        if ($this->isTimeValue($value)) {
            return new StaticCall(
                new Name('FieldValue'),
                'asFloat',
                [new Node\Arg($value)]
            );
        }

        if ($this->isFloatValue($value)) {
            return new StaticCall(
                new Name('FieldValue'),
                'asFloat',
                [new Node\Arg($value)]
            );
        }

        // Default to asInt
        return new StaticCall(
            new Name('FieldValue'),
            'asInt',
            [new Node\Arg($value)]
        );
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
}
