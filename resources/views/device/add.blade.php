@extends('layouts.librenmsv1')

@section('title', __('Add Device'))

@section('content')
    <div class="container">
        <x-panel>
            <x-slot name="title">
                <i class="fa fa-plus fa-fw fa-lg" aria-hidden="true"></i> {{ __('Add Device') }}
            </x-slot>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="tw:list-disc tw:list-inside tw:space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('device.add.store') }}"
                  x-data="{
                      snmpEnabled: true,
                      methods: {
                          @foreach($availableMethods as $method)
                              '{{ $method['type'] }}': {
                                  enabled: {{ $method['type'] === 'snmp' || $method['type'] === 'icmp' ? 'true' : 'false' }},
                                  credential_mode: 'default'
                              },
                          @endforeach
                      }
                  }">
                @csrf

                <!-- General Properties -->
                <div class="tw:grid tw:grid-cols-1 tw:md:grid-cols-2 tw:gap-4">
                    <div class="form-group {{ $errors->has('hostname') ? 'has-error' : '' }}">
                        <label for="hostname" class="control-label">{{ __('Hostname or IP') }}</label>
                        <input type="text" id="hostname" name="hostname" class="form-control" value="{{ old('hostname') }}" placeholder="device.example.com" required autofocus>
                        @if($errors->has('hostname'))
                            <span class="help-block">{{ $errors->first('hostname') }}</span>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="port_assoc_mode" class="control-label">{{ __('Port Association Mode') }}</label>
                        <select id="port_assoc_mode" name="port_assoc_mode" class="form-control">
                            @foreach($port_association_modes as $mode)
                                <option value="{{ $mode }}" {{ old('port_assoc_mode', $default_port_association_mode) === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="tw:grid tw:grid-cols-1 tw:md:grid-cols-3 tw:gap-4">
                    <div class="form-group">
                        <label for="port" class="control-label">{{ __('SNMP Port') }}</label>
                        <input type="text" id="port" name="port" class="form-control" placeholder="161" value="{{ old('port') }}">
                    </div>

                    <div class="form-group">
                        <label for="transport" class="control-label">{{ __('Transport') }}</label>
                        <select id="transport" name="transport" class="form-control">
                            @foreach($transports as $transport)
                                <option value="{{ $transport }}" {{ old('transport') === $transport ? 'selected' : '' }}>{{ $transport }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="poller_group" class="control-label">{{ __('Poller Group') }}</label>
                        <select id="poller_group" name="poller_group" class="form-control">
                            <option value="0">{{ __('Default poller group') }}</option>
                            @foreach($poller_groups as $id => $name)
                                <option value="{{ $id }}" {{ old('poller_group', $default_poller_group) == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="tw:grid tw:grid-cols-1 tw:md:grid-cols-2 tw:gap-4 tw:mb-6">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="force_add" value="1" {{ old('force_add') ? 'checked' : '' }}>
                            <strong>{{ __('Force add') }}</strong><br>
                            <span class="text-muted">{{ __('No ICMP or SNMP checks performed') }}</span>
                        </label>
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="ping_fallback" value="1" {{ old('ping_fallback') ? 'checked' : '' }}>
                            <strong>{{ __('Ping fallback') }}</strong><br>
                            <span class="text-muted">{{ __('Fallback to Ping-only if SNMP fails') }}</span>
                        </label>
                    </div>
                </div>

                <hr class="tw:my-6">

                <!-- Polling Methods Section -->
                <h3 class="tw:text-lg tw:font-semibold tw:mb-4">{{ __('Polling Methods') }}</h3>
                <div class="tw:flex tw:flex-col tw:gap-4 tw:mb-6">
                    @foreach($availableMethods as $method)
                        <div class="tw:border tw:border-gray-200 tw:dark:tw:border-dark-gray-400 tw:rounded-lg tw:p-4">
                            <div class="checkbox tw:m-0">
                                <label class="tw:font-semibold">
                                    <input type="hidden" name="polling_methods[{{ $method['type'] }}][enabled]" value="0">
                                    <input type="checkbox"
                                           name="polling_methods[{{ $method['type'] }}][enabled]"
                                           value="1"
                                           x-model="methods['{{ $method['type'] }}'].enabled"
                                           @if($method['type'] === 'snmp') @change="snmpEnabled = methods['snmp'].enabled" @endif>
                                    {{ $method['label'] }}
                                </label>
                            </div>

                            <div x-show="methods['{{ $method['type'] }}'].enabled" class="tw:mt-4 tw:pl-6 tw:border-l-2 tw:border-blue-500">
                                @if(!empty($method['schema_fields']))
                                    <!-- Credential Mode Picker -->
                                    <div class="form-group">
                                        <label class="control-label">{{ __('Credential Mode') }}</label>
                                        <div class="tw:flex tw:gap-4">
                                            <label class="radio-inline">
                                                <input type="radio" name="polling_methods[{{ $method['type'] }}][credential_mode]" value="default" x-model="methods['{{ $method['type'] }}'].credential_mode"> {{ __('Attempt Defaults') }}
                                            </label>
                                            <label class="radio-inline">
                                                <input type="radio" name="polling_methods[{{ $method['type'] }}][credential_mode]" value="existing" x-model="methods['{{ $method['type'] }}'].credential_mode"> {{ __('Use Existing Secret') }}
                                            </label>
                                            <label class="radio-inline">
                                                <input type="radio" name="polling_methods[{{ $method['type'] }}][credential_mode]" value="new" x-model="methods['{{ $method['type'] }}'].credential_mode"> {{ __('Create New Secret') }}
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Existing Secret Picker -->
                                    <div x-show="methods['{{ $method['type'] }}'].credential_mode === 'existing'" class="form-group">
                                        <label class="control-label">{{ __('Select Secret') }}</label>
                                        <select name="polling_methods[{{ $method['type'] }}][secret_id]" class="form-control">
                                            <option value="">{{ __('Select an existing secret...') }}</option>
                                            @foreach($availableSecrets[$method['type']] ?? [] as $secret)
                                                <option value="{{ $secret->id }}" {{ old("polling_methods.{$method['type']}.secret_id") == $secret->id ? 'selected' : '' }}>
                                                    {{ $secret->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- New Secret Form -->
                                    <div x-show="methods['{{ $method['type'] }}'].credential_mode === 'new'">
                                        <div class="form-group">
                                            <label class="control-label">{{ __('Secret Description') }}</label>
                                            <input type="text" name="polling_methods[{{ $method['type'] }}][description]" class="form-control" placeholder="{{ __('Optional') }}" value="{{ old("polling_methods.{$method['type']}.description") }}">
                                        </div>
                                        <div class="checkbox">
                                            <label>
                                                <input type="hidden" name="polling_methods[{{ $method['type'] }}][default]" value="0">
                                                <input type="checkbox" name="polling_methods[{{ $method['type'] }}][default]" value="1" {{ old("polling_methods.{$method['type']}.default") ? 'checked' : '' }}> {{ __('Make Default') }}
                                            </label>
                                        </div>

                                        <!-- Dynamic Secret Form Fields -->
                                        <div class="tw:grid tw:grid-cols-1 tw:md:grid-cols-2 tw:gap-4" x-data="{ formData: @js($method['schema_defaults']) }">
                                            @foreach($method['schema_fields'] as $field)
                                                <div class="form-group" x-show="{{ $field['visible_if_expression'] ?: 'true' }}">
                                                    <label class="control-label">{{ __($field['label']) }}</label>
                                                    @if($field['field_type'] === 'select')
                                                        <select name="polling_methods[{{ $method['type'] }}][secret_data][{{ $field['key'] }}]" x-model="formData['{{ $field['key'] }}']" class="form-control">
                                                            @foreach($field['options'] as $optVal => $optLabel)
                                                                <option value="{{ $optVal }}">{{ __($optLabel) }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($field['field_type'] === 'password')
                                                        <input type="password" name="polling_methods[{{ $method['type'] }}][secret_data][{{ $field['key'] }}]" class="form-control" autocomplete="new-password">
                                                    @else
                                                        <input type="text" name="polling_methods[{{ $method['type'] }}][secret_data][{{ $field['key'] }}]" x-model="formData['{{ $field['key'] }}']" class="form-control">
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Settings Fields -->
                                @if(!empty($method['settings_fields']))
                                    <div class="tw:mt-4 tw:pt-4 tw:border-t tw:border-gray-200 tw:dark:tw:border-dark-gray-400">
                                        <h4 class="tw:text-sm tw:font-semibold tw:mb-3">{{ __('Settings') }}</h4>
                                        <div class="tw:grid tw:grid-cols-1 tw:md:grid-cols-2 tw:gap-4" x-data="{ settingsData: {} }">
                                            @foreach($method['settings_fields'] as $setting)
                                                <div class="form-group" x-show="{{ $setting['visible_if_expression'] ?: 'true' }}">
                                                    <label class="control-label">{{ __('poller.method_settings.' . $method['type'] . '.' . $setting['key']) }}</label>
                                                    @if(($setting['field_type'] ?? 'text') === 'select')
                                                        <select name="polling_methods[{{ $method['type'] }}][settings][{{ $setting['key'] }}]" x-model="settingsData['{{ $setting['key'] }}']" class="form-control">
                                                            @foreach($setting['options'] ?? [] as $optVal => $optLabel)
                                                                <option value="{{ $optVal }}">{{ __($optLabel) }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif(($setting['field_type'] ?? 'text') === 'number')
                                                        <input type="number" name="polling_methods[{{ $method['type'] }}][settings][{{ $setting['key'] }}]" x-model="settingsData['{{ $setting['key'] }}']" class="form-control"
                                                               @if(isset($setting['min'])) min="{{ $setting['min'] }}" @endif
                                                               @if(isset($setting['max'])) max="{{ $setting['max'] }}" @endif>
                                                    @else
                                                        <input type="text" name="polling_methods[{{ $method['type'] }}][settings][{{ $setting['key'] }}]" x-model="settingsData['{{ $setting['key'] }}']" class="form-control">
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Manual Overrides (Shown only if SNMP is Disabled) -->
                <div x-show="!snmpEnabled" class="tw:border tw:border-gray-200 tw:dark:tw:border-dark-gray-400 tw:rounded-lg tw:p-4 tw:mb-6" style="display: none;">
                    <h3 class="tw:text-base tw:font-semibold tw:mb-4">{{ __('Manual Overrides') }}</h3>
                    <div class="tw:grid tw:grid-cols-1 tw:md:grid-cols-3 tw:gap-4">
                        <div class="form-group">
                            <label for="sysName" class="control-label">{{ __('sysName') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <input type="text" id="sysName" name="sysName" class="form-control" value="{{ old('sysName') }}">
                        </div>
                        <div class="form-group">
                            <label for="hardware" class="control-label">{{ __('Hardware') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <input type="text" id="hardware" name="hardware" class="form-control" value="{{ old('hardware') }}">
                        </div>
                        <div class="form-group" x-init="setTimeout(() => init_select2('#os-select', 'os', {}, null, '{{ __('OS (optional)') }}'), 100)">
                            <label for="os-select" class="control-label">{{ __('OS') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <select id="os-select" name="os" class="form-control"></select>
                        </div>
                    </div>
                </div>

                <div class="tw:mt-6">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-plus tw:mr-1"></i> {{ __('Add Device') }}
                    </button>
                </div>
            </form>
        </x-panel>
    </div>
@endsection
