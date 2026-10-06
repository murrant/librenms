<?php

namespace App\Http\Requests;

use App\Facades\LibrenmsConfig;
use App\Graphing\Exceptions\GraphException;
use App\Graphing\Exceptions\InvalidGraphInput;
use App\Graphing\GraphAccess;
use App\Graphing\GraphQuery;
use App\Graphing\GraphRegistry;
use App\Graphing\GraphService;
use App\Graphing\ResolvedGraph;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use LibreNMS\Util\Time;
use LibreNMS\Util\Url;

class GraphRequest extends FormRequest
{
    /** Requested start, defaults to one day ago */
    public int $from = 0;
    /** Requested end, 0 when graphing up to now */
    public int $to = 0;

    private bool $rangeParsed = false;
    private ?GraphQuery $graphQuery = null;
    private ?ResolvedGraph $graph = null;
    private ?GraphException $graphError = null;

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(Url::parseLegacyPathVars($this->path()));
    }

    /**
     * Only checks that access is established (a user, or a guest trusted by AuthenticateGraph).
     * The graph is resolved and authorized after validation, see passedValidation().
     */
    public function authorize(): bool
    {
        try {
            $this->access();

            return true;
        } catch (GraphException $e) {
            $this->graphError = $e;

            return false;
        }
    }

    /**
     * Resolve the graph once input is known to be valid, so subject resolution and
     * authorization never see invalid input.
     */
    protected function passedValidation(): void
    {
        try {
            $this->graph = app(GraphService::class)->resolve($this->graphQuery(), $this->access());
        } catch (GraphException $e) {
            $this->graphError = $e;
            $this->failedAuthorization();
        }
    }

    protected function failedAuthorization(): void
    {
        // graph images respond with an error image instead of an error page
        if ($this->expectsImage() && $this->graphError !== null) {
            throw $this->graphError;
        }

        parent::failedAuthorization();
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsImage()) {
            throw InvalidGraphInput::fromValidation(new ValidationException($validator));
        }

        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        $baseRules = [
            'type' => ['required', 'string', 'regex:/^[a-zA-Z0-9]+_[a-zA-Z0-9_.-]+$/'],
            'from' => ['nullable', 'string', 'regex:/^[-a-zA-Z0-9_ :]+$/'],
            'to' => ['nullable', 'string', 'regex:/^[-a-zA-Z0-9_ :]+$/'],
            'widescreen' => ['nullable', 'string', 'in:yes,no'],
            'legend' => ['nullable', 'string', 'in:yes,no,true,false,1,0'],
            'previous' => ['nullable', 'string', 'in:yes,no'],
            'showcommand' => ['nullable', 'string', 'in:yes,no'],
            'port_speed_zoom' => ['nullable', 'in:0,1'],
            'device' => ['nullable', 'integer'],
            'id' => ['nullable', 'regex:/^\d+(,\d+)*$/'],
            'width' => ['nullable', 'integer', 'min:10'],
            'height' => ['nullable', 'integer', 'min:10'],

            // Collectd parameters
            'c_plugin' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'c_plugin_instance' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'c_type' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'c_type_instance' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/'],

            // Sensor parameters
            'sensor' => ['nullable', 'integer'],

            // Generic parameters commonly used by legacy graph scripts
            'in' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'out' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'inverse' => ['nullable', 'string', 'in:true,false,1,0,yes,no'],
            'float_precision' => ['nullable', 'integer'],
            'total' => ['nullable', 'string', 'in:true,false,1,0,yes,no'],
            'details' => ['nullable', 'string', 'in:true,false,1,0,yes,no'],
            'aggregate' => ['nullable', 'string', 'in:true,false,1,0,yes,no'],
        ];

        if (preg_match('/^([a-zA-Z0-9]+)_(.+)$/', $this->string('type')->toString(), $matches)) {
            return array_merge($baseRules, app(GraphRegistry::class)->rules($matches[1], $matches[2]));
        }

        return $baseRules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $validateItem = function (string $key, mixed $value) use ($validator, &$validateItem): void {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $validateItem($key, $k);
                        $validateItem($key, $v);
                    }
                } elseif (is_string($value)) {
                    if (! preg_match('/^[-a-zA-Z0-9_.: \/+,]*$/', $value)) {
                        $validator->errors()->add($key, 'The parameter value contains invalid characters.');
                    }
                }
            };

            foreach ($this->all() as $key => $value) {
                // Validate key
                if (! preg_match('/^[a-zA-Z0-9_.-]+$/', $key)) {
                    $validator->errors()->add($key, 'The parameter key contains invalid characters.');
                }
                $validateItem($key, $value);
            }
        });
    }

    public function graphQuery(): GraphQuery
    {
        return $this->graphQuery ??= GraphQuery::fromVars($this->toVars());
    }

    /**
     * @throws GraphException
     */
    public function access(): GraphAccess
    {
        return GraphAccess::fromRequest($this);
    }

    /**
     * The graph resolved and authorized for this request
     *
     * @throws GraphException
     */
    public function graph(): ResolvedGraph
    {
        return $this->graph ??= app(GraphService::class)->resolve($this->graphQuery(), $this->access());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function toVars(array $overrides = []): array
    {
        $this->parseRange();

        $vars = $this->except(['page', 'username', 'password']);
        $vars['from'] = $this->from;
        $vars['to'] = $this->to ?: null;

        return array_merge($vars, $overrides);
    }

    private function parseRange(): void
    {
        if ($this->rangeParsed) {
            return;
        }

        $this->from = (int) (Time::parseAt($this->input('from', '')) ?: LibrenmsConfig::get('time.day'));
        $this->to = Time::parseAt($this->input('to', ''));
        $this->rangeParsed = true;
    }

    private function expectsImage(): bool
    {
        return $this->routeIs('graph');
    }
}
