@props(['transceiver', 'portlink' => true])

<div class="tw-flex sm:tw-flex-row tw-flex-col" {{ $attributes }}>
    <div class="tw-pr-8">
        @if($portlink)<x-port-link :port="$transceiver->port"></x-port-link>@endif
        @if($transceiver->vendor || $transceiver->type)<p class="tw-text-2xl tw-font-bold">{{ $transceiver->vendor }} {{ $transceiver->type }}</p>@endif
        @if($transceiver->model)<p>PN:{{ $transceiver->model }}</p>@endif
        @if($transceiver->serial)<p>SN:{{ $transceiver->serial }}</p>@endif
    </div>
    <div>
        <p>@if($transceiver->revision)Rev: {{ $transceiver->revision }}@endif @if($transceiver->date)Date: {{ $transceiver->date }}@endif</p>
        @if($transceiver->distance)<p>Distance: {{ $transceiver->distance }}m</p>@endif
        @if($transceiver->wavelength)<p>Wavelength: {{ $transceiver->wavelength }}nm</p>@endif
        @if($transceiver->connector)<p>Connector: {{ $transceiver->connector }}</p>@endif
        @if($transceiver->channels > 1)<p>Channels: {{ $transceiver->channels }}</p>@endif
    </div>
</div>
