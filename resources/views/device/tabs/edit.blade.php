@extends('device.index')

@section('tab')
    <x-option-bar :options="$data['edit_sections']" :selected="$data['edit_section']"></x-option-bar>

    @includeFirst(['device.tabs.edit.' . $data['edit_section'], 'device.tabs.edit.legacy'])
@endsection
