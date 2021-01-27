<div class="grid-stack-item">
<div class="panel panel-default shadow">
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
            <div class="col-sm-4">@lang('System Name')</div>
            <div class="col-sm-8">{{ $device->sysName }}</div>
        </div>
    </div>
</div>
</div>
