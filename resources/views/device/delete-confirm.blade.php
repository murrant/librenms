@extends('layouts.librenmsv1')

@section('title', __('device.delete_device'))

@section('content')
    <div class="tw:max-w-3xl tw:mx-auto tw:py-10 tw:px-4">

        <div class="tw:rounded-lg tw:border tw:border-gray-200 tw:dark:border-dark-gray-100 tw:bg-white tw:dark:bg-dark-gray-400 tw:shadow-sm tw:overflow-hidden">

            <div class="tw:flex tw:items-center tw:text-2xl tw:gap-3 tw:bg-red-50 tw:dark:bg-red-900/30 tw:border-b tw:border-red-100 tw:dark:border-red-800 tw:px-6 tw:py-4">
                <i class="fa fa-warning fa-3x tw:text-red-800 tw:dark:text-dark-white-100"></i>
                <h2 class="tw:text-2xl tw:font-semibold tw:text-red-800 tw:dark:text-dark-white-100!">
                    {{ __('device.delete', ['name' => $device->displayName()]) }}
                </h2>
            </div>

            <div class="tw:px-6 tw:py-6 tw:space-y-4">

                <p class="tw:text-gray-700 tw:dark:text-gray-300">
                    {{ __('device.confirm_delete', ['hostname' => $device->hostname]) }}
                </p>

                <div class="tw:rounded-md tw:bg-red-50 tw:dark:bg-red-900/30 tw:border tw:border-red-100 tw:dark:border-red-800 tw:px-4 tw:py-3 tw:text-red-700 tw:dark:text-red-300 tw:space-y-1">
                    <p class="tw:font-semibold">{{ __('device.warning_monitored') }}</p>
                    <p>{{ __('device.warning_data') }}
                        <ul>
                            @foreach($data_warn as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </p>
                </div>

            </div>

            <div class="tw:flex tw:justify-end tw:gap-3 tw:border-t tw:border-gray-100 tw:dark:border-dark-gray-100 tw:px-6 tw:py-4">

                <a href="{{ url()->previous() }}"
                class="tw:px-4 tw:py-2 tw:font-medium tw:rounded-md tw:no-underline tw:bg-white tw:dark:bg-gray-700 tw:text-gray-700 tw:dark:text-gray-300 tw:hover:bg-gray-50 tw:dark:hover:bg-gray-600 tw:transition-colors"
                >
                {{ __('Cancel') }}
                </a>

                <form action="{{ route('device.destroy', $device) }}">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class=" tw:px-4 tw:py-2 tw:font-medium tw:rounded-md tw:bg-red-600 tw:text-white! tw:hover:bg-red-700 tw:transition-colors"
                    >
                        <i class="fa fa-trash"></i>
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>

        </div>

    </div>
@endsection
