@extends('widgets.settings.base')

@php
    $sev_val = (array) ($filter['rule.severity']['in'] ?? $filter['severity']['in'] ?? $severity ?? []);
    $state_val = (string) ($filter['state']['eq'] ?? $state ?? '');
    $rule_id_val = $filter['rule_id']['eq'] ?? ($rule_id ?? '');
@endphp

@section('form')
    <div class="form-group">
        <label for="title-{{ $id }}" class="control-label">{{ __('Widget title') }}</label>
        <input type="text" class="form-control" name="title" id="title-{{ $id }}" placeholder="{{ __('Custom title') }}" value="{{ $title }}">
    </div>
    <div class="form-group">
        <label for="state-{{ $id }}" class="control-label">{{ __('State') }}:</label>
        <select class="form-control" name="state" id="state-{{ $id }}">
            <option value="">{{ __('any state') }}</option>
            @foreach($states as $name => $val)
                <option value="{{ $val }}" @if($state_val === (string)$val) selected @endif>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label for="severity-{{ $id }}" class="control-label">{{ __('Displayed severity') }}:</label>
        <select class="form-control" name="severity[]" id="severity-{{ $id }}" multiple>
            @foreach($severities as $name => $val)
                <option value="{{ $val }}" @if(in_array($val, $sev_val) || $sev_val == $val) selected @endif>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label for="rule_id-{{ $id }}" class="control-label">{{ __('Alert Rule') }}</label>
        <select class="form-control" name="rule_id" id="rule_id-{{ $id }}" data-placeholder="{{ __('All Rules') }}">
            @if(isset($rule) && $rule)
                <option value="{{ $rule->id }}" selected>{{ $rule->name }}</option>
            @endif
        </select>
    </div>
    <div class="form-group">
        <label for="device_group-{{ $id }}" class="control-label">{{ __('Device group') }}</label>
        <select class="form-control" name="device_group" id="device_group-{{ $id }}" data-placeholder="{{ __('All Devices') }}">
            @if($device_group)
                <option value="{{ $device_group->id }}" selected>{{ $device_group->name }}</option>
            @endif
        </select>
    </div>
    <div class="form-group">
        <label for="proc-{{ $id }}" class="control-label">{{ __('Show Procedure field') }}:</label>
        <select class="form-control" name="proc" id="proc-{{ $id }}">
            <option value="1" @if($proc == 1) selected @endif>{{ __('show') }}</option>
            <option value="0" @if($proc == 0) selected @endif>{{ __('hide') }}</option>
        </select>
    </div>
    <div class="form-group">
        <label for="location-{{ $id }}" class="control-label">{{ __('Show Location field') }}:</label>
        <select class="form-control" name="location" id="location-{{ $id }}">
            <option value="1" @if($location == 1) selected @endif>{{ __('show') }}</option>
            <option value="0" @if($location == 0) selected @endif>{{ __('hide') }}</option>
        </select>
    </div>
    <div class="form-group">
        <label for="uncollapse_key_count-{{ $id }}" class="control-label">{{ __('Automatic uncollapse Alert if length below characters') }}:</label>
        <input class="form-control" type="uncollapse_key_count" min="1" step="1" name="uncollapse_key_count" id="uncollapse_key_count-{{ $id }}" value="{{ $uncollapse_key_count }}">
    </div>
    <div class="form-group">
        <label for="sort-{{ $id }}" class="control-label">{{ __('Sort alerts by') }}:</label>
        <select class="form-control" name="sort" id="sort-{{ $id }}">
            <option value="" @if($sort == 1) selected @endif>{{ __('timestamp, descending') }}</option>
            <option value="severity" @if($sort == 0) selected @endif>{{ __('severity, descending') }}</option>
        </select>
    </div>
    <div class="form-group">
        <label for="hidenavigation-{{ $id }}" class="control-label">{{ __('Hide Navigation') }}:</label>
        <input type="checkbox" class="form-control" name="hidenavigation" id="hidenavigation-{{ $id }}" value="{{ $hidenavigation }}" data-size="normal" @if($hidenavigation) checked @endif>
    </div>

@endsection

@section('javascript')
    <script type="text/javascript">
        init_select2('#device_group-{{ $id }}', 'device-group', {});
        init_select2('#rule_id-{{ $id }}', 'alert-rule', {});

        $('#hidenavigation-{{ $id }}')
            .bootstrapSwitch('offColor','danger')
            .on('switchChange.bootstrapSwitch', function (e, data) {
                var hidenav = $(this).is(':checked') ? "1": "0";
                $('#hidenavigation-{{ $id }}').val(hidenav);
            });
    </script>
@endsection
