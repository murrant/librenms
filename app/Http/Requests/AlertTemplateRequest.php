<?php

namespace App\Http\Requests;

use App\Models\AlertTemplate;
use App\Models\Device;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use LibreNMS\Alert\AlertData;

class AlertTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Redundancy here checks before validation is run.
     */
    public function authorize(): bool
    {
        $templateModel = $this->route('alert_template');

        if ($templateModel) {
            return Gate::allows('update', $templateModel);
        }

        return Gate::allows('create', AlertTemplate::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
            'title_rec' => ['nullable', 'string', 'max:255'],
            'rules' => ['nullable', 'array'],
            'rules.*' => ['integer', 'exists:alert_rules,id'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => isset($this->name) ? strip_tags((string) $this->name) : $this->name, // FIXME remove when safe
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->isMethod('POST') && $this->name === 'Default Alert Template') {
                $v->errors()->add('name', 'This template name is reserved!');
            }

            $fields = [
                'template' => $this->template,
                'title' => $this->title,
                'title_rec' => $this->title_rec,
            ];

            $test_data = [
                'id' => 0,
                'rule' => 'test',
                'name' => 'Test Rule',
                'severity' => 'critical',
                'extra' => '',
                'disabled' => 0,
                'query' => '',
                'builder' => [],
                'proc' => '',
                'invert_map' => 0,
                'notes' => '',
            ];
            $test_device = new Device(['hostname' => 'test']);
            $test_device->device_id = 0;
            $test_data['alert'] = new AlertData(AlertData::testData($test_device));

            foreach ($fields as $fieldName => $templateString) {
                if (empty($templateString) || ! is_string($templateString)) {
                    continue;
                }

                try {
                    Blade::render($templateString, $test_data, true); // unsafe
                } catch (\Throwable $e) {
                    $v->errors()->add($fieldName, "The $fieldName field has invalid template syntax: " . $e->getMessage());
                }
            }
        });
    }
}
