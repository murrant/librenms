@extends('layouts.librenmsv1')

@section('content')
    <x-device.page :device="$device">
        <x-panel>
            @php
                $views = $data['views'];
                $view = $data['view'];
            @endphp
            <x-slot name="title">
                <span class="tw:font-bold">Services</span> &#187;<x-option-bar :options="$views" :selected="$view"
                                                                               border="none"
                                                                               class="tw:inline-block tw:p-0!"></x-option-bar>
            </x-slot>
            @foreach($devices ?? [$device] as $device)
                <x-panel body-class="tw:p-0!">
                    @if(! empty($devices))
                        <x-slot name="title">
                            {!! \LibreNMS\Util\Url::modernDeviceLink($device, vars: ['tab' => 'services']) !!}
                        </x-slot>
                    @endif
                    @if($device->services->isNotEmpty())
                        <table class="table table-hover table-condensed">
                            <thead>
                            <td class="col-sm-2"><strong>Name</strong></td>
                            <td class="col-sm-1"><strong>Check Type</strong></td>
                            <td class="col-sm-1"><strong>Remote Host</strong></td>
                            <td class="col-sm-4"><strong>Message</strong></td>
                            <td class="col-sm-2"><strong>Description</strong></td>
                            <td class="col-sm-1"><strong>Last Changed</strong></td>
                            <td class="col-sm-1"></td>
                            </thead>
                            <tbody>
                    @endif
                    @foreach($device->services as $service)
                                <tr id="row_{{ $service->service_id }}">
                                    <td class="col-sm-2">
                                        <span class="alert-status {{ \LibreNMS\Util\Html::severityLabelClass($service->statusAsSeverity()) }}">
                                            <span class="device-services-page text-nowrap">{{ $service->service_name }}</span>
                                        </span>
                                    </td>
                                    <td class="col-sm-1 text-muted">{{ $service->service_type }}</td>
                                    <td class="col-sm-1 text-muted">{{ $service->service_ip }}</td>
                                    <td class="col-sm-4">{!! nl2br(e($service->service_message)) !!}</td>
                                    <td class="col-sm-2 text-muted">{{ $service->service_desc }}</td>
                                    <td class="col-sm-1 text-muted" title="{{ $service->updated_at }}">{{ \LibreNMS\Util\Time::formatInterval($service->updated_at?->diffInSeconds(), true, 2) }}</td>
                                    <td class="col-sm-1">
                                        @can('admin')
                                            <div class="btn-group">
                                                <button type='button' class='btn btn-primary btn-sm' aria-label='Edit' data-toggle='modal' data-target='#create-service' data-service_id='{{ $service->service_id }}' name='edit-service'><i class='fa fa-pencil' aria-hidden='true'></i></button>
                                                <button type='button' class='btn btn-danger btn-sm' aria-label='Delete' data-toggle='modal' data-target='#confirm-delete' data-service_id='{{ $service->service_id }}' name='delete-service'><i class='fa fa-trash' aria-hidden='true'></i></button>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                                @if($view == 'details')
                                <tr>
                                    <td colspan="7">
                                        <x-graph-row columns="responsive" type="service_graph" :device="$service->device_id" :vars="['id' => $service->service_id]"></x-graph-row>
                                    </td>
                                </tr>
                                @endif
                    @endforeach
                    @if($device->services->isNotEmpty())
                            </tbody>
                        </table>
                    @endif
                </x-panel>
            @endforeach
        </x-panel>
    </x-device.page>
@endsection
