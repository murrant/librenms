@extends('layouts.librenmsv1')

@section('title', __('Add Device'))

@section('content')
    <div class="container">
        <div id="app">
            <add-device :data="{{ json_encode($data) }}" inline-template>
                <div class="row">
                    <div class="col-md-offset-1 col-md-10">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <div class="panel-title">@lang('Add Device')
                                    <div class="pull-right checkbox-inline">
                                        <label>
                                            <toggle-button
                                                    name="@lang('Advanced')"
                                                    :value="advanced"
                                                    :sync="true"
                                                    @change="toggleAdvanced"
                                            ></toggle-button>
                                            @lang('Advanced')
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-body">
                                <div class="form-horizontal">
                                    <div class="form-group">
                                        <label for="hostname" class="col-sm-3 control-label">@lang('Hostname or IP') *</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" id="hostname" placeholder="@lang('Hostname')">
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced" @if(!$data['advanced']) style="display:none" @endif>
                                        <label for="ip" class="col-sm-3 control-label">@lang('Override IP')</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" id="ip" placeholder="IP">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">@lang('Type')</label>
                                        <div class="col-sm-9">
                                            <div class="btn-group">
                                                <button class="btn btn-primary" :class="{active: type === 'snmpv1'}" @click="type = 'snmpv1'">SNMP v1</button>
                                                <button class="btn btn-primary" :class="{active: type === 'snmpv2'}" @click="type = 'snmpv2'">SNMP v2</button>
                                                <button class="btn btn-primary" :class="{active: type === 'snmpv3'}" @click="type = 'snmpv3'">SNMP v3</button>
                                                <button class="btn btn-primary" :class="{active: type === 'ping'}" @click="type = 'ping'">Ping</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="type === 'snmpv1' || type === 'snmpv2'">
                                        <label for="community" class="col-sm-3 control-label">Community</label>
                                        <div class="col-sm-6">
                                            <input type="text" class="form-control" id="community" placeholder="@lang('Leave blank to use default')">
                                        </div>
                                        <div class="col-sm-3">
                                            <a href="{{ route('settings') . '/poller/snmp' }}"><button class="btn btn-default">@lang('Configure Default')</button></a>
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="type !== 'ping'">
                                        <label class="col-sm-3 control-label">@lang('Transport')</label>

                                            <div class="col-sm-3" v-show="advanced" @if(!$data['advanced']) style="display:none" @endif>
                                                <label class="sr-only" for="port">@lang('Port')</label>
                                                <input type="text" class="form-control" name="port" id="port" placeholder="@lang('Port')" v-model="port">
                                            </div>
                                            <div class="col-sm-3" v-show="advanced" @if(!$data['advanced']) style="display:none" @endif>
                                                <label for="proto" class="sr-only">@lang('Protocol')</label>
                                                <select name="proto" id="proto" class="form-control" v-model="proto">
                                                    <option value="udp">UDP</option>
                                                    <option value="tcp">TCP</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-3">
                                                <label for="ipt" class="sr-only">@lang('IP')</label>
                                                <select name="ipt" id="ipt" class="form-control" v-model="transport">
                                                    <option value="4">IPv4</option>
                                                    <option value="6">IPv6</option>
                                                </select>
                                            </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="sysName" class="col-sm-3 control-label">@lang('sysName')</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" id="sysName" placeholder="@lang('sysName')" v-model="sysname">
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="os" class="col-sm-3 control-label">@lang('OS')</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" id="os" placeholder="@lang('OS')" v-model="os">
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="hardware" class="col-sm-3 control-label">@lang('Hardware')</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" id="hardware" placeholder="@lang('Hardware')" v-model="hardware">
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced" @if(!$data['advanced']) style="display:none" @endif>
                                        <label for="port_association" class="col-sm-3 control-label">Port Association</label>
                                        <div class="col-sm-3">
                                            <select name="port_association" id="port_association" class="form-control" v-model="port_association">
                                                @foreach(\App\Models\Port::associationModes() as $mode)
                                                    <option value="{{ $mode }}">{{ $mode }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </add-device>
        </div>
    </div>
@endsection

@push('styles')
    <link href="{{ asset(mix('/css/app.css')) }}" rel="stylesheet">
@endpush

@push('scripts')
    @routes
    <script>
        import AddDevice from "../../js/components/AddDevice";

        export default {
            components: {AddDevice}
        }
    </script>
    <script src="{{ asset(mix('/js/lang/' . app()->getLocale() . '.js')) }}"></script>
    <script src="{{ asset(mix('/js/manifest.js')) }}"></script>
    <script src="{{ asset(mix('/js/vendor.js')) }}"></script>
    <script src="{{ asset(mix('/js/app.js')) }}"></script>
@endpush
