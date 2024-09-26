<div class="row">
    <div class="col-md-12">
        <x-panel class="device-overview panel-condensed">
            <x-slot name="heading" class="tw-mb-6">
                <x-icons.transceiver></x-icons.transceiver>
                <strong><a href="{{ $transceivers_link }}">{{ __('port.tabs.transceivers') }}</a></strong>
            </x-slot>

            @foreach($transceivers as $transceiver)
                <x-panel body-class="!tw-p-0">
                    <x-slot name="heading">
                        @if($transceiver->port)
                        <x-port-link :port="$transceiver->port"></x-port-link>
                        @endif
                        <x-icons.transceiver></x-icons.transceiver> {{ $transceiver->vendor }} {{ $transceiver->type }}
                    </x-slot>
                    <table class="table table-hover table-condensed table-striped !tw-mb-0">
                        @foreach($sensors as $sensor)
                            @if($sensor->entPhysicalIndex !== null && $sensor->entPhysicalIndex == $transceiver->entity_physical_index && $filterSensors($sensor))
                            <tr>
                                <td>{{ $sensor->sensor_descr }}</td>
                                <td><x-graph loading="lazy" type="sensor_{{ $sensor->sensor_class }}" width="100" height="24" :vars="['id' => $sensor->sensor_id]"></x-graph></td>
                                <td><x-label :status="$sensor->currentStatus()">{{ $sensor->formatValue() }}</x-label></td>
                            </tr>
                            @endif
                        @endforeach
                    </table>
                </x-panel>
            @endforeach
        </x-panel>
    </div>
</div>
