@extends('layouts.librenmsv1')

@section('content')
<x-device.page :device="$device">
    @if($data['mode'] === 'detail')
        <div class="well well-sm">
            <h3 class="panel-title">{{ $data['sla_name'] }}</h3>
        </div>

        <x-panel :type="$data['is_danger'] ? 'danger' : 'default'">
            @foreach($data['detail_graphs'] as $detailGraph)
                <x-graph-row :device="$device" :type="$detailGraph['type']" :title="$detailGraph['title']" columns="responsive" :vars="['id' => $data['sla']->sla_id]" />
            @endforeach
        </x-panel>
    @else
        <x-panel>
            <x-slot name="heading">
                <div class="tw:flex tw:items-center tw:justify-between tw:gap-2">
                    <x-option-bar border="none" name="{{ __('SLA') }}" :options="$data['type_options']" :selected="$data['view']" />
                    <x-option-bar border="none" name="{{ __('Status') }}" :options="$data['status_options']" :selected="$data['opstatus']" />
                </div>
            </x-slot>

            @forelse($data['slas'] as $sla)
                <x-graph-row :device="$device" :type="'device_sla'" columns="responsive" :vars="['id' => $sla['id']]" @class(['tw:bg-red-50 tw:dark:bg-red-950/20 tw:rounded tw:p-2' => $sla['is_danger']])>
                    <x-slot name="title">
                        <span @class(['tw:text-red-600 tw:dark:text-red-400' => $sla['is_danger']])>
                            @if($sla['has_detail'])
                                <a href="{{ $sla['detail_link'] }}" @class(['tw:text-red-600! tw:dark:text-red-400!' => $sla['is_danger']])>{{ $sla['name'] }}</a>
                            @else
                                {{ $sla['name'] }}
                            @endif
                        </span>
                    </x-slot>
                </x-graph-row>
            @empty
                <div class="alert alert-info">{{ __('No SLAs found for this device.') }}</div>
            @endforelse
        </x-panel>
    @endif
</x-device.page>
@endsection
