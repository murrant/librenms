@extends('device.submenu')

@section('tabcontent')
    <x-dashboard>
        <livewire:device-overview-widget />
    </x-dashboard>
@endsection


@push('styles')
    <style type="text/css">

    </style>
@endpush
