@props(['transceiver'])

@foreach($transceiver->metrics->sort(fn($a, $b) => $a->defaultOrder() <=> $b->defaultOrder())->groupBy('type') as $type => $metrics)
    @if($loop->first)
        <div class="tw-grid tw-grid-cols-[min-content_1fr] tw-gap-x-4"  {{ $attributes }}>
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
            <x-slot name="title">{{ $transceiver->port?->getLabel() }}</x-slot>
            <x-slot name="body">
                <x-graph-row loading="lazy" :type="'port_transceiver_' . $type" :port="$transceiver->port" legend="yes"></x-graph-row>
            </x-slot>
        </x-popup>
    </div>
    @if($loop->last)
        </div>
    @endif
@endforeach
