<div class="grid-stack-item">
<div class="panel panel-default shadow device-overview">
    <div class="panel-heading dash-widget-header">
        <span class="fade-edit pull-right">
            <i class="fa fa-pencil-square-o" data-widget-id="1" aria-label="Settings" data-toggle="tooltip" data-placement="top" title="Settings"></i>
            <i class="text-danger fa fa-times" data-widget-id="1" aria-label="Close" data-toggle="tooltip" data-placement="top" title="Remove"></i>
        </span>
        <span id="dash-widget-title"><i class="fa fa-id-card fa-lg icon-theme" aria-hidden="true"></i>
            <strong>{{ $device->sysDescr }}</strong>
        </span>
    </div>
    <div class="grid-stack-item-content panel-body dash-widget-body">
        <div class="row">
            <div class="col-sm-4">@lang('device.attributes.sysName')</div>
            <div class="col-sm-8">{{ $device->sysName }}</div>
        </div>
        @if($ip)
        <div class='row'>
            <div class='col-sm-4'>@lang("device.ip.$ip_type")</div>
            <div class='col-sm-8'>{{ $ip }}</div>
        </div>
        @endif
        @if($device->purpose)
            <div class="row">
                <div class="col-sm-4">@lang('device.attributes.purpose')</div>
                <div class="col-sm-8">{{ $device->purpose }}</div>
            </div>
        @endif
        @if($hardware)
            <div class="row">
                <div class="col-sm-4">@lang('device.attributes.hardware')</div>
                <div class="col-sm-8">{{ $hardware }}</div>
            </div>
        @endif
        @if($os)
            <div class="row">
                <div class="col-sm-4">@lang('device.attributes.os')</div>
                <div class="col-sm-8">{{ $os }}</div>
            </div>
        @endif
        @if($device->serial)
            <div class="row">
                <div class="col-sm-4">@lang('device.attributes.serial')</div>
                <div class="col-sm-8">{{ $device->serial }}</div>
            </div>
        @endif
        @if($device->sysObjectID)
            <div class="row">
                <div class="col-sm-4">@lang('device.attributes.sysObjectID')</div>
                <div class="col-sm-8">{{ $device->sysObjectID }}</div>
            </div>
        @endif
        @if($device->contact)
            <div class="row">
                <div class="col-sm-4">@lang('device.attributes.sysContact')</div>
                <div class="col-sm-8">{{ $contact }}</div>
            </div>
        @endif
        @if($date_added)
            <div class="row">
                <div class="col-sm-4">@lang('device.attributes.inserted')</div>
                <div class="col-sm-8">{{ $date_added }}</div>
            </div>
        @endif
    </div>
</div>
</div>
