<h3 class="tw:text-xl tw:font-semibold tw:mb-6 tw:text-slate-800 tw:dark:text-dark-white-100 tw:border-b tw:pb-3 tw:dark:border-dark-gray-400">{{ __('Add Polling Type') }}</h3>

@if($unconfiguredMethods->isEmpty())
    <div class="tw:bg-blue-50 tw:text-blue-800 tw:p-4 tw:rounded-lg tw:border tw:border-blue-200 tw:dark:bg-dark-blue-900/30 tw:dark:text-blue-300 tw:dark:border-dark-blue-800">
        <i class="fa fa-info-circle tw:mr-2"></i> {{ __('All available polling types are already configured for this device.') }}
    </div>
@else
    <form method="POST" action="{{ route('device.edit.polling.store', $device) }}" x-data="{ methodType: '', credentialMode: 'existing' }">
        @csrf

        <div class="tw:mb-5">
            <label class="tw:block tw:font-medium tw:mb-2 tw:text-slate-700 tw:dark:text-dark-white-200">{{ __('Polling Type') }}</label>
            <select name="method_type" x-model="methodType" class="form-control" required>
                <option value="">{{ __('Select a polling type...') }}</option>
                @foreach($unconfiguredMethods as $method)
                    <option value="{{ $method['type']->value }}">{{ $method['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="methodType !== ''" style="display: none;" x-transition>
            <div class="tw:mb-6 tw:p-4 tw:bg-slate-50 tw:dark:bg-dark-gray-600 tw:rounded-lg tw:border tw:border-slate-200 tw:dark:border-dark-gray-400">
                <label class="tw:block tw:font-medium tw:mb-3 tw:text-slate-700 tw:dark:text-dark-white-200">{{ __('Credential Mode') }}</label>
                <div class="tw:flex tw:gap-6">
                    <label class="tw:flex tw:items-center tw:cursor-pointer tw:group">
                        <input type="radio" name="credential_mode" value="existing" x-model="credentialMode" class="tw:w-4 tw:h-4 tw:text-blue-600 tw:border-gray-300 focus:tw:ring-blue-500 tw:mr-2">
                        <span class="group-hover:tw:text-blue-600 tw:transition-colors">{{ __('Existing Secret') }}</span>
                    </label>
                    <label class="tw:flex tw:items-center tw:cursor-pointer tw:group">
                        <input type="radio" name="credential_mode" value="new" x-model="credentialMode" class="tw:w-4 tw:h-4 tw:text-blue-600 tw:border-gray-300 focus:tw:ring-blue-500 tw:mr-2">
                        <span class="group-hover:tw:text-blue-600 tw:transition-colors">{{ __('Create New Secret') }}</span>
                    </label>
                </div>
            </div>

            <div x-show="credentialMode === 'existing'" class="tw:mb-6" style="display: none;" x-transition>
                <label class="tw:block tw:font-medium tw:mb-2 tw:text-slate-700 tw:dark:text-dark-white-200">{{ __('Select Secret') }}</label>
                <select name="secret_id" class="form-control" :required="credentialMode === 'existing'">
                    <option value="">{{ __('Select an existing secret...') }}</option>
                    @foreach($unconfiguredMethods as $method)
                        @if(isset($availableSecrets[$method['type']->value]))
                            <optgroup label="{{ $method['label'] }}" x-show="methodType === '{{ $method['type']->value }}'">
                                @foreach($availableSecrets[$method['type']->value] as $secret)
                                    <option value="{{ $secret->id }}">{{ $secret->description }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>

            <div x-show="credentialMode === 'new'" class="tw:mb-6" style="display: none;" x-transition>
                <div class="tw:mb-5">
                    <label class="tw:block tw:font-medium tw:mb-2 tw:text-slate-700 tw:dark:text-dark-white-200">{{ __('Description') }}</label>
                    <input type="text" name="description" class="form-control">
                    <p class="tw:text-xs tw:text-slate-500 tw:mt-1">{{ __('Optional. Leave blank to auto-generate.') }}</p>
                </div>
                <div class="tw:mb-6" x-data="{ isDefault: false }">
                    <label class="tw:flex tw:items-center tw:cursor-pointer tw:group tw:bg-slate-50 tw:dark:bg-dark-gray-600 tw:px-4 tw:py-3 tw:rounded-lg tw:border tw:border-slate-200 tw:dark:border-dark-gray-400 tw:w-auto tw:inline-flex">
                        <div class="tw:relative tw:flex-shrink-0">
                            <input type="checkbox" name="default" value="1" class="tw:sr-only" x-model="isDefault">
                            <div class="tw:block tw:w-10 tw:h-6 tw:rounded-full tw:transition-colors tw:duration-200" :class="isDefault ? 'tw:bg-blue-600' : 'tw:bg-gray-300 tw:dark:bg-gray-700'"></div>
                            <div class="tw:absolute tw:left-1 tw:top-1 tw:bg-white tw:w-4 tw:h-4 tw:rounded-full tw:transition-transform tw:duration-200" :class="isDefault ? 'tw:translate-x-4' : 'tw:translate-x-0'"></div>
                        </div>
                        <span class="tw:ml-3 tw:font-medium tw:text-slate-700 tw:dark:text-dark-white-200">{{ __('Make Default') }}</span>
                    </label>
                </div>

                @foreach($unconfiguredMethods as $method)
                    <div x-show="methodType === '{{ $method['type']->value }}'" class="tw:bg-slate-50 tw:dark:bg-dark-gray-600 tw:p-5 tw:rounded-lg tw:border tw:border-slate-200 tw:dark:border-dark-gray-400">
                        <h4 class="tw:font-medium tw:text-lg tw:mb-4 tw:border-b tw:pb-2 tw:border-slate-200 tw:dark:border-dark-gray-500">{{ $method['label'] }} {{ __('Details') }}</h4>

                        <div x-data="{ formData: {
                            @foreach($method['schema'] as $key => $field)
                                {{ $key }}: '{{ $field['default'] ?? (isset($field['options']) ? array_key_first($field['options']) : '') }}',
                            @endforeach
                        } }">
                            @foreach($method['schema'] as $key => $field)
                                <div class="tw:mb-4"
                                    @if(isset($field['visible_if']))
                                        x-show="
                                        @foreach($field['visible_if'] as $condKey => $condVal)
                                            @if(is_array($condVal) && isset($condVal['$in']))
                                                {{ json_encode($condVal['$in']) }}.includes(formData['{{ $condKey }}'])
                                            @else
                                                formData['{{ $condKey }}'] === '{{ $condVal }}'
                                            @endif
                                            @if(!$loop->last) && @endif
                                        @endforeach
                                        "
                                    @endif
                                >
                                    <label class="tw:block tw:font-medium tw:mb-1 tw:text-slate-700 tw:dark:text-dark-white-200">{{ __($field['label']) }}</label>
                                    @if(($field['type'] ?? 'text') === 'select')
                                        <select name="{{ $key }}" x-model="formData['{{ $key }}']" class="form-control" :disabled="methodType !== '{{ $method['type']->value }}' || credentialMode !== 'new'">
                                            @foreach($field['options'] as $optVal => $optLabel)
                                                <option value="{{ $optVal }}">{{ __($optLabel) }}</option>
                                            @endforeach
                                        </select>
                                    @elseif(($field['type'] ?? 'text') === 'password')
                                        <input type="password" name="{{ $key }}" x-model="formData['{{ $key }}']" class="form-control" autocomplete="new-password" :disabled="methodType !== '{{ $method['type']->value }}' || credentialMode !== 'new'">
                                    @else
                                        <input type="text" name="{{ $key }}" x-model="formData['{{ $key }}']" class="form-control" :disabled="methodType !== '{{ $method['type']->value }}' || credentialMode !== 'new'">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="tw:mt-6 tw:pt-6 tw:border-t tw:border-slate-200 tw:dark:border-dark-gray-400">
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-plus tw:mr-1"></i> {{ __('Add Polling Type') }}
                </button>
            </div>
        </div>
    </form>
@endif