@section('widget-title')
    <i class="fa fa-id-card fa-lg icon-theme" aria-hidden="true"></i> <strong>
        {{ $device->sysDescr }}
    </strong>
@endsection

@section('widget-content')
<div class="row">
    <div class="col-sm-4">System Name</div>
    <div class="col-sm-8">{{ $device->sysName }}</div>
</div>
@endsection
