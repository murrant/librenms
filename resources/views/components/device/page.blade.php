@section('title', $pagetitle)

<div class="container-fluid">
    <x-panel>
        <x-slot:bare class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-2 tw:w-full">
                <img src="{{ url($device->logo()) }}" title="{{ $device->logo() }}"
                     alt="logo"
                     class="device-icon-header tw:dark:bg-gray-50 tw:dark:rounded-lg tw:dark:p-2 tw:shrink-0"
                     style="max-height: 100px">
                <div class="tw:grow tw:shrink tw:basis-40 tw:mr-auto">
                    <div class="tw:text-nowrap">
                        @if($parentDeviceId)
                            <a href="{{ route('device', $parentDeviceId) }}" title="{{ __('device.vm_host') }}"><i
                                    class="fa fa-server fa-fw fa-lg"></i></a>
                        @endif
                        @if($device->isUnderMaintenance())
                            <span title="{{ __('device.scheduled_maintenance') }}" class="fa fa-wrench fa-fw fa-lg"></span>
                        @endif
                        <span style="font-size: 20px;">
                    <x-device-link :device="$device"/>
                    @if($typeIcon)
                                <i class="fa-solid fa-{{ $typeIcon }}" title="{{ $typeText }}"></i>
                            @endif
                </span>
                    </div>
                    <a href="{{ url('/devices/location=' . urlencode((string) $device->location)) }}">{{ $device->location }}</a>
                </div>
            <div class="tw:flex tw:flex-wrap tw:gap-2 tw:grow tw:shrink-0 tw:basis-56 tw:min-w-37.5 tw:justify-end">
                @foreach($overviewGraphs() as $graph)
                    <div class='tw:rounded tw:text-center tw:w-37.5 tw:max-sm:w-auto tw:max-sm:grow'>
                        <x-graph-popup :vars="$graph" :type="$graph['type']" :width="$graph['width']" :height="$graph['height']"
                                       :popup-title="$graph['popup_title']" :device="$device"></x-graph-popup>
                        <div style='font-weight: bold; font-size: 7pt; margin: -3px;'>{{ $graph['popup_title'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-slot:slot>
    </x-panel>

    <x-device.page-tabs :device="$device" :dropdown-links="$dropdownLinks"/>

    <div class="tab-content tw:mt-4">
        <div class="tab-pane active">

            {{ $slot }}

        </div>
    </div>
</div>
