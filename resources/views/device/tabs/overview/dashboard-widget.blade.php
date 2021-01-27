<div class="grid-stack-item panel panel-default shadow">
    <div class="panel-heading dash-widget-header">
        <span class="fade-edit pull-right">
            <i class="fa fa-pencil-square-o edit-widget" data-widget-id="1" aria-label="Settings" data-toggle="tooltip" data-placement="top" title="Settings"></i>
            <i class="text-danger fa fa-times close-widget" data-widget-id="1" aria-label="Close" data-toggle="tooltip" data-placement="top" title="Remove"></i>
        </span>
        <span id="dash-widget-title">@yield('widget-title')</span>
    </div>
    <div class="grid-stack-item-content panel-body dash-widget-header-body">
        @yield('widget-content')
    </div>
</div>
