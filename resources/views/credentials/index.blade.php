@extends('layouts.librenmsv1')

@section('title', trans('credentials.title'))

@section('content')
    <x-panel title="{{ trans('credentials.title') }}" class="md:tw-mx-4 sm:tw-mx-0">
        <div class="tw-flex tw-flex-col" style="margin: -15px">
            <div class="tw-overflow-x-auto">
                <div class="tw-mt-2 tw-align-middle tw-inline-block tw-min-w-full">
                    {{ $dataTable->table() }}
                </div>
            </div>
        </div>
    </x-panel>

@endsection

@push('scripts')
    {{ $dataTable->scripts() }}
@endpush

@push('styles')
    <style>
        .dt-buttons {
            margin-left: 0.5rem;
        }
        .dataTables_filter {
            margin-right: 0.5rem;
        }
    </style>
@endpush
