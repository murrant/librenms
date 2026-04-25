@extends('layouts.librenmsv1')

@section('content')
    <x-device.page :device="$device">
        <x-device.edit-tabs :device="$device" />

        @if($methods->isNotEmpty())
            <div x-data="{ activeTab: '{{ $methods->first()['type']->value }}' }" class="tw:flex tw:flex-col md:tw:flex-row tw:gap-6 tw:mt-6">
                <!-- Left Tabs -->
                <div class="tw:w-full md:tw:w-1/4 tw:shrink-0">
                    <ul class="tw:flex tw:flex-col tw:space-y-2">
                        @foreach($methods as $method)
                            <li>
                                <button type="button" @click="activeTab = '{{ $method['type']->value }}'"
                                        :class="activeTab === '{{ $method['type']->value }}' ? 'tw:bg-blue-600 tw:text-white tw:border-blue-600' : 'tw:bg-white tw:text-slate-700 tw:border-slate-200 hover:tw:bg-slate-50 tw:dark:bg-dark-gray-500 tw:dark:text-dark-white-200 tw:dark:border-dark-gray-400 tw:dark:hover:bg-dark-gray-400'"
                                        class="tw:w-full tw:text-left tw:px-4 tw:py-3 tw:border tw:rounded-lg tw:flex tw:justify-between tw:items-center tw:transition-colors tw:shadow-sm">
                                    <span class="tw:font-medium">{{ $method['label'] }}</span>
                                    <div class="tw:flex tw:items-center">
                                        <!-- Status Icon Placeholder -->
                                        <i class="fa fa-fw fa-circle-o tw:text-gray-400 status-icon-placeholder" title="{{ __('Status placeholder') }}"></i>
                                    </div>
                                </button>
                            </li>
                        @endforeach
                        <li class="tw:mt-4 tw:pt-2 tw:border-t tw:border-slate-200 tw:dark:border-dark-gray-400">
                            <button type="button" @click="activeTab = 'add'"
                                    :class="activeTab === 'add' ? 'tw:bg-green-600 tw:text-white tw:border-green-600' : 'tw:bg-green-50 tw:text-green-700 tw:border-green-200 hover:tw:bg-green-100 tw:dark:bg-dark-green-900/30 tw:dark:text-green-400 tw:dark:border-dark-green-800'"
                                    class="tw:w-full tw:text-left tw:px-4 tw:py-3 tw:border tw:rounded-lg tw:flex tw:justify-between tw:items-center tw:transition-colors tw:shadow-sm">
                                <span class="tw:font-medium">{{ __('Add Polling Type') }}</span>
                                <i class="fa fa-plus"></i>
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Right Content -->
                <div class="tw:w-full md:tw:w-3/4 tw:bg-white tw:dark:bg-dark-gray-500 tw:border tw:border-slate-200 tw:dark:border-dark-gray-400 tw:rounded-lg tw:shadow-sm tw:p-6 tw:flex-grow">
                    @foreach($methods as $method)
                        <div x-show="activeTab === '{{ $method['type']->value }}'" style="display: none;" x-transition>
                            <h3 class="tw:text-xl tw:font-semibold tw:mb-6 tw:text-slate-800 tw:dark:text-dark-white-100 tw:border-b tw:pb-3 tw:dark:border-dark-gray-400">{{ $method['label'] }} {{ __('Settings') }}</h3>

                            <form method="POST" action="{{ route('device.edit.polling.update', ['device' => $device, 'methodType' => $method['type']->value]) }}">
                                @csrf
                                @method('PUT')

                                <div class="tw:mb-6" x-data="{ isDisabled: {{ !$method['enabled'] ? 'true' : 'false' }} }">
                                    <label class="tw:flex tw:items-center tw:cursor-pointer tw:group tw:bg-slate-50 tw:dark:bg-dark-gray-600 tw:px-4 tw:py-3 tw:rounded-lg tw:border tw:border-slate-200 tw:dark:border-dark-gray-400 tw:w-full tw:max-w-md">
                                        <div class="tw:relative tw:flex-shrink-0">
                                            <input type="checkbox" name="disabled" value="1" class="tw:sr-only" x-model="isDisabled">
                                            <div class="tw:block tw:w-10 tw:h-6 tw:rounded-full tw:transition-colors tw:duration-200" :class="isDisabled ? 'tw:bg-blue-600' : 'tw:bg-gray-300 tw:dark:bg-gray-700'"></div>
                                            <div class="tw:absolute tw:left-1 tw:top-1 tw:bg-white tw:w-4 tw:h-4 tw:rounded-full tw:transition-transform tw:duration-200" :class="isDisabled ? 'tw:translate-x-4' : 'tw:translate-x-0'"></div>
                                        </div>
                                        <span class="tw:ml-3 tw:font-medium tw:text-slate-700 tw:dark:text-dark-white-200">{{ __('Disable polling for') }} {{ $method['label'] }}</span>
                                    </label>
                                </div>

                                <div class="tw:mb-6">
                                    <h4 class="tw:font-semibold tw:text-lg tw:mb-3 tw:text-slate-700 tw:dark:text-dark-white-200">{{ __('Configured Secret') }}</h4>
                                    <div class="tw:bg-slate-50 tw:dark:bg-dark-gray-600 tw:border tw:border-slate-200 tw:dark:border-dark-gray-400 tw:p-4 tw:rounded-lg tw:text-sm">
                                        <div class="tw:grid tw:grid-cols-1 md:tw:grid-cols-2 tw:gap-y-3 tw:gap-x-6">
                                            <div class="tw:flex tw:flex-col">
                                                <span class="tw:text-slate-500 tw:dark:text-dark-white-400 tw:uppercase tw:text-xs tw:font-bold tw:mb-1">{{ __('Description') }}</span>
                                                <span class="tw:font-medium tw:text-slate-800 tw:dark:text-dark-white-100">{{ $method['secret']?->description }}</span>
                                            </div>
                                            @foreach($method['secret']->data ?? [] as $key => $val)
                                                <div class="tw:flex tw:flex-col">
                                                    <span class="tw:text-slate-500 tw:dark:text-dark-white-400 tw:uppercase tw:text-xs tw:font-bold tw:mb-1">{{ __(ucfirst($key)) }}</span>
                                                    <span class="tw:font-medium tw:text-slate-800 tw:dark:text-dark-white-100">{{ is_bool($val) ? ($val ? __('Yes') : __('No')) : ($val === null || $val === '' ? '-' : $val) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="tw:flex tw:items-center tw:gap-4 tw:mt-6 tw:pt-6 tw:border-t tw:border-slate-200 tw:dark:border-dark-gray-400">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save tw:mr-1"></i> {{ __('Save Settings') }}
                                    </button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('device.edit.polling.destroy', ['device' => $device, 'methodType' => $method['type']->value]) }}" class="tw:mt-4">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('{{ __('Are you sure you want to remove this polling method?') }}')">
                                    <i class="fa fa-trash tw:mr-1"></i> {{ __('Remove') }} {{ $method['label'] }}
                                </button>
                            </form>
                        </div>
                    @endforeach

                    <!-- Add Polling Type Content (When Tabs Exist) -->
                    <div x-show="activeTab === 'add'" style="display: none;" x-transition>
                        @include('device.edit.includes.add-polling-type')
                    </div>
                </div>
            </div>
        @else
            <!-- No configured methods, just show the Add form -->
            <div class="tw:mt-6 tw:bg-white tw:dark:bg-dark-gray-500 tw:border tw:border-slate-200 tw:dark:border-dark-gray-400 tw:rounded-lg tw:shadow-sm tw:p-6 tw:max-w-4xl tw:mx-auto">
                @include('device.edit.includes.add-polling-type')
            </div>
        @endif
    </x-device.page>
@endsection
