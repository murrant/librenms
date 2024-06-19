@extends('device.submenu')

@section('tabcontent')
    <table class="table table-hover table-condensed table-striped">
        <thead>
            <tr>
                <th style="width: 150px;">{{ __('VLAN Number') }}</th>
                <th style="width: 250px;">{{ __('VLAN Name') }}</th>
                <th>{{ __('Ports') }}</th>
            </tr>
        </thead>
        <tbody>

        @foreach($data['vlans'] as $vlan_number => $vlans)
            <tr>
                <td>{{ $vlan_number }}</td>
                <td>{{ $vlans->first()->vlan_name }}</td>
                <td>
                @foreach($vlans as $vlan)
                    @if(!$vlan->port)
                        @continue;
                    @endif

                    @if(!$vars)
                        <span class="tw-inline-flex">
                            <x-port-link :port="$vlan->port">{{ $vlan->port->getShortLabel() }}</x-port-link>
                            @if($vlan->untagged)<span>&nbsp;(U)</span>@endif
                            @if(!$loop->last)<span>,</span>@endif
                        </span>
                    @else
                        <div class="minigraph-div">
                            <x-port-link :port="$vlan->port" :graphs="[['type' => $data['graph_type']]]">
                                <div class="tw-font-bold">{{ $vlan->port->getShortLabel() }}</div>
                                <x-graph :port="$vlan->port" :type="$data['graph_type']" :from="$data['from']" width="132" height="48" legend="no"></x-graph>
                            </x-port-link>
                        </div>
                    @endif
                @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection



