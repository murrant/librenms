@extends('layouts.librenmsv1')

@section('content')
<x-device.page :device="$device">
    @if($data['mode'] === 'detail')
        <div class="well well-sm">
            <h3 class="panel-title">{{ $data['sla_name'] }}</h3>
        </div>

        <div class="panel panel-default {{ $data['is_danger'] ? 'panel-danger' : '' }}">
            @foreach($data['detail_graphs'] as $detailGraph)
                <div class="panel-heading">
                    <h3 class="panel-title">{{ $detailGraph['title'] }}</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <x-graph-row :device="$device" :type="$detailGraph['type']" :vars="['id' => $data['sla']->sla_id]" />
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="tw:flex tw:flex-col md:tw:flex-row md:tw:justify-between tw:gap-2">
            <x-option-bar name="{{ __('SLA') }}" :options="$data['type_options']" :selected="$data['view']" />
            <x-option-bar name="{{ __('Status') }}" :options="$data['status_options']" :selected="$data['opstatus']" />
        </div>

        @forelse($data['slas'] as $sla)
            <div class="panel panel-default {{ $sla['is_danger'] ? 'panel-danger' : '' }}">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        @if($sla['has_detail'])
                            <a href="{{ $sla['detail_link'] }}">{{ $sla['name'] }}</a>
                        @else
                            {{ $sla['name'] }}
                        @endif
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <x-graph-row :device="$device" :type="'device_sla'" :vars="['id' => $sla['id']]" />
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-info">{{ __('No SLAs found for this device.') }}</div>
        @endforelse
    @endif
</x-device.page>
@endsection
