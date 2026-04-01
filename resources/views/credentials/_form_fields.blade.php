@foreach($schema as $field => $config)
    @php
        // $model is now an array
        $value = old($field, $model[$field] ?? '');
        $type = $config['type'] ?? 'text';
        $label = $config['label'] ?? ucfirst($field);
        $options = $config['options'] ?? [];
        $visibleIf = isset($config['visible_if']) ? json_encode($config['visible_if']) : 'null';
    @endphp

    <div class="form-group dynamic-field {{ $errors->has($field) ? 'has-error' : '' }}" data-visible-if="{{ $visibleIf }}" id="group-{{ $field }}">
        <label for="{{ $field }}" class="control-label">{{ __($label) }}</label>

        @if($type === 'select')
            <select name="{{ $field }}" id="{{ $field }}" class="form-control">
                @foreach($options as $val => $text)
                    <option value="{{ $val }}" {{ (string)$value === (string)$val ? 'selected' : '' }}>
                        {{ __($text) }}
                    </option>
                @endforeach
            </select>
        @else
            <input type="{{ $type }}" class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ $value }}">
        @endif

        @if($errors->has($field))
            <span class="help-block">{{ $errors->first($field) }}</span>
        @endif
    </div>
@endforeach

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fields = document.querySelectorAll('.dynamic-field');

        function evaluateCondition(condition, fieldValue) {
            if (typeof condition === 'object' && condition !== null) {
                if (condition.$in) {
                    return condition.$in.includes(fieldValue);
                }
            }
            return fieldValue === condition;
        }

        function checkVisibility() {
            fields.forEach(field => {
                const conditionStr = field.getAttribute('data-visible-if');
                if (conditionStr && conditionStr !== 'null') {
                    try {
                        const conditions = JSON.parse(conditionStr);
                        let isVisible = true;

                        for (const [depField, condition] of Object.entries(conditions)) {
                            const depElement = document.getElementById(depField);
                            if (depElement) {
                                if (!evaluateCondition(condition, depElement.value)) {
                                    isVisible = false;
                                    break;
                                }
                            }
                        }

                        if (isVisible) {
                            field.style.display = 'block';
                            const innerInputs = field.querySelectorAll('input, select');
                            innerInputs.forEach(input => input.disabled = false);
                        } else {
                            field.style.display = 'none';
                            const innerInputs = field.querySelectorAll('input, select');
                            innerInputs.forEach(input => input.disabled = true);
                        }
                    } catch (e) {
                        console.error('Error parsing visibility conditions', e);
                    }
                }
            });
        }

        const allInputs = document.querySelectorAll('input, select');
        allInputs.forEach(input => {
            input.addEventListener('change', checkVisibility);
        });

        checkVisibility();
    });
</script>
