@extends('device.index')

@section('tab')
    @foreach($data['transceivers'] as $transceiver)
        <x-panel>
            <x-slot name="heading">
                <div class="tw-flex sm:tw-flex-row tw-flex-col">
                    <div class="tw-pr-8">
                        <x-port-link :port="$transceiver->port"></x-port-link>
                        @if($transceiver->vendor || $transceiver->type)<h4>{{ $transceiver->vendor }} {{ $transceiver->type }}</h4>@endif
                        @if($transceiver->model)<p>PN:{{ $transceiver->model }}</p>@endif
                        @if($transceiver->serial)<p>SN:{{ $transceiver->serial }}</p>@endif
                    </div>
                    <div>
                        <p>@if($transceiver->revision)Rev: {{ $transceiver->revision }}@endif @if($transceiver->date)Date: {{ $transceiver->date }}@endif</p>
                        @if($transceiver->distance)<p>Distance: {{ $transceiver->distance }}m</p>@endif
                        @if($transceiver->wavelength)<p>Wavelength: {{ $transceiver->wavelength }}</p>@endif
                        @if($transceiver->channels > 1)<p>Channels: {{ $transceiver->channels }}</p>@endif
                    </div>
                </div>
            </x-slot>
                @foreach($transceiver->metrics->groupBy('type') as $type => $metrics)
                    @if($loop->first)
                    <div class="tw-grid tw-grid-cols-[min-content_1fr] tw-gap-x-4">
                    @endif
                       <div class="tw-whitespace-nowrap">
                           {{  trans_choice('port.transceivers.metrics.' . $type, 0) }}:
                           {{ $metrics->firstWhere('channel', 0)?->value ?? $metrics->avg('value') }} {{ __('port.transceivers.units.' . $type) }}
                       </div>
                       <div>
                           <x-popup>
                               <div class="tw-border-2">
                                   <x-graph :type="'port_transceiver_' . $type" :port="$transceiver->port" legend="yes" width="100" height="20"></x-graph>
                               </div>
                               <x-slot name="title">{{ $transceiver->port->getLabel() }}</x-slot>
                               <x-slot name="body">
                                   <x-graph-row loading="lazy" :type="'port_transceiver_' . $type" :port="$transceiver->port" legend="yes"></x-graph-row>
                               </x-slot>
                           </x-popup>
                       </div>
                    @if($loop->last)
                    </div>
                    @endif
                @endforeach
        </x-panel>
    @endforeach
@endsection
