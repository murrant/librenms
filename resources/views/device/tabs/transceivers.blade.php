@extends('device.index')

@section('tab')
    @foreach($data['transceivers'] as $transceiver)
        <x-panel>
            <x-slot name="heading">
                <x-port-link :port="$transceiver->port"></x-port-link>
                <h4>
                    {{ $transceiver->vendor }} {{ $transceiver->type }}

                </h4>
                PN:{{ $transceiver->model }} SN:{{ $transceiver->serial }}
            </x-slot>
            <div>
                @foreach($transceiver->metrics->groupBy('channel')->map->keyBy('type') as $channel => $channelMetrics)
                    <div>
                        Channel {{ $channel }}

                        @php($rxpower = $channelMetrics->pull('power-rx'))
                        <div>
                            Rx Power: {{ $rxpower->value }} dBm
                        </div>

                        @php($txpower = $channelMetrics->pull('power-tx'))
                        <div>
                            Rx Power: {{ $rxpower->value }} dBm
                        </div>

                    @foreach($channelMetrics as $type => $metric)
                   <div>
                       Metric: {{ $type }}
                       <div>
                       {!! json_encode($metric) !!}}

                       </div>
                   </div>
                    @endforeach
                    </div>
                @endforeach
            </div>
        </x-panel>
    @endforeach
@endsection
