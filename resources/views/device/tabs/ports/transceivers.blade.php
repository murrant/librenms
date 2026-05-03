@foreach($data['transceivers'] as $transceiver)
    <x-panel>
        <x-slot:heading>
            <x-transceiver :transceiver="$transceiver"></x-transceiver>
        </x-slot:heading>
        <x-transceiver-sensors :transceiver="$transceiver"></x-transceiver-sensors>
    </x-panel>
@endforeach
