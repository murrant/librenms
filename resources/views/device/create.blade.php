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
                                <form class="form-horizontal" @submit.prevent="addDevice">
                                    @csrf
                                    <div class="form-group">
                                        <label for="hostname" class="col-sm-3 control-label">@lang('Hostname or IP')</label>
                                        <div class="col-sm-9">
                                            <div class="input-group">
                                                <input type="text" class="form-control input-sm" id="hostname" placeholder="@lang('Hostname')" v-model="hostname">
                                                <span class="input-group-addon" id="basic-addon2"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced" @if(!$data['advanced']) style="display:none" @endif>
                                        <label for="override_ip" class="col-sm-3 control-label">@lang('Override IP')</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control input-sm" id="override_ip" placeholder="IP" v-model="override_ip">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">@lang('Type')</label>
                                        <div class="col-sm-9">
                                            <button-group class="btn-group btn-group-sm" v-model="type">
                                                <button type="button" class="btn btn-sm btn-default" value="snmpv1">SNMP v1</button>
                                                <button type="button" class="btn btn-sm btn-default active" value="snmpv2">SNMP v2</button>
                                                <button type="button" class="btn btn-sm btn-default" value="snmpv3">SNMP v3</button>
                                                <button type="button" class="btn btn-sm btn-default" value="ping">Ping</button>
                                            </button-group>
                                        </div>
                                    </div>

                                    <!-- SNMPv1/2 Options -->

                                    <div class="form-group" v-show="type === 'snmpv1' || type === 'snmpv2'">
                                        <label for="community" class="col-sm-3 control-label">Community</label>
                                        <div class="col-sm-6">
                                            <input type="text" class="form-control input-sm" id="community" placeholder="@lang('Leave blank to use default')">
                                            <i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i>
                                        </div>
                                        <div class="col-sm-3">
                                            <a href="{{ route('settings') . '/poller/snmp' }}">
                                                <button type="button" class="btn btn-default btn-sm">@lang('Configure Default')</button>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type !== 'ping'" @if(!$data['advanced']) style="display:none" @endif>
                                        <label class="col-sm-3 control-label">@lang('Transport')</label>

                                        <div class="col-sm-3">
                                            <label class="sr-only" for="port">@lang('Port')</label>
                                            <input type="text" class="form-control input-sm" id="port" placeholder="@lang('Port')" v-model="port">
                                        </div>
                                        <div class="col-sm-3">
                                            <label for="proto" class="sr-only">@lang('Protocol')</label>
                                            <button-group class="btn-group btn-group-sm" v-model="proto">
                                                <button type="button" class="btn btn-sm btn-default active" value="udp">UDP</button>
                                                <button type="button" class="btn btn-sm btn-default" value="tcp">TCP</button>
                                            </button-group>
                                        </div>
                                        <div class="col-sm-3">
                                            <label for="ipt" class="sr-only">@lang('IP')</label>
                                            <button-group class="btn-group btn-group-sm" v-model="transport">
                                                <button type="button" class="btn btn-sm btn-default active" value="auto">@lang('Auto')</button>
                                                <button type="button" class="btn btn-sm btn-default" value="4">IPv4</button>
                                                <button type="button" class="btn btn-sm btn-default" value="6">IPv6</button>
                                            </button-group>
                                        </div>
                                    </div>

                                    <!-- Ping Options -->

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="sysName" class="col-sm-3 control-label">@lang('sysName')</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control input-sm" id="sysName" placeholder="@lang('sysName')" v-model="sysname">
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="os" class="col-sm-3 control-label">@lang('OS')</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control input-sm" id="os" placeholder="@lang('OS')" v-model="os">
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="hardware" class="col-sm-3 control-label">@lang('Hardware')</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control input-sm" id="hardware" placeholder="@lang('Hardware')" v-model="hardware">
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced" @if(!$data['advanced']) style="display:none" @endif>
                                        <label for="port_association" class="col-sm-3 control-label">Port Association</label>
                                        <div class="col-sm-3">
                                            <select name="port_association" id="port_association" class="form-control input-sm" v-model="port_association">
                                                @foreach(\App\Models\Port::associationModes() as $mode)
                                                    <option value="{{ $mode }}">{{ $mode }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- SNMP v3 Options -->

                                    <div class="form-group" v-show="type === 'snmpv3'" style="display:none">
                                        <label for="auth_level" class="col-sm-3 control-label">Auth Level</label>
                                        <div class="col-sm-9">
                                            <button-group class="btn-group btn-group-sm" v-model="auth_level">
                                                <button type="button" class="btn btn-sm btn-default active" value="noAuthNoPriv">@lang('noAuthNoPriv')</button>
                                                <button type="button" class="btn btn-sm btn-default" value="authNoPriv">@lang('authNoPriv')</button>
                                                <button type="button" class="btn btn-sm btn-default" value="authPriv">@lang('authPriv')</button>
                                            </button-group>
                                        </div>
                                    </div>
                                    <div class="form-group" v-show="type === 'snmpv3' && auth_level !== 'noAuthNoPriv'" style="display:none">
                                        <label for="auth_name" class="col-sm-3 control-label">@lang('Auth User Name')</label>
                                        <div class="col-sm-6">
                                            <input type="text" placeholder="@lang('User')" id="auth_name" class="form-control input-sm" autocomplete="off" v-model="auth_name">
                                        </div>
                                    </div>
                                    <div class="form-group" v-show="type === 'snmpv3' && auth_level !== 'noAuthNoPriv'" style="display:none">
                                        <label for="auth_pass" class="col-sm-3 control-label">@lang('Auth Password')</label>
                                        <div class="col-sm-6">
                                            <input type="text"placeholder="@lang('Password')" id="auth_pass" class="form-control input-sm" autocomplete="off" v-model="auth_pass">
                                        </div>
                                        <div class="col-sm-3" v-show="advanced">
                                            <button-group class="btn-group btn-group-sm" v-model="auth_algo" title="@lang('Auth Algorithm')">
                                                <button type="button" class="btn btn-sm btn-default active" value="MD5">MD5</button>
                                                <button type="button" class="btn btn-sm btn-default" value="SHA">SHA</button>
                                            </button-group>
                                        </div>
                                    </div>
                                    <div class="form-group" v-show="type === 'snmpv3' && auth_level === 'authPriv'" style="display:none">
                                        <label for="crypto_pass" class="col-sm-3 control-label">@lang('Crypto Password')</label>
                                        <div class="col-sm-6">
                                            <input type="text" placeholder="@lang('Crypto Password')" id="crypto_pass" class="form-control input-sm" autocomplete="off" v-model="crypto_pass">
                                        </div>
                                        <div class="col-sm-3" v-show="advanced">
                                            <button-group class="btn-group btn-group-sm" v-model="crypto_algo" title="@lang('Crypto Algorithm')">
                                                <button type="button" class="btn btn-sm btn-default active" value="AES">AES</button>
                                                <button type="button" class="btn btn-sm btn-default" value="DES">DES</button>
                                            </button-group>
                                        </div>
                                    </div>


                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button id="add" type="submit" class="btn btn-primary btn-sm">@lang('Add Device')</button>
                                        </div>
                                    </div>
                                </form>
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
    <script src="{{ asset(mix('/js/lang/' . app()->getLocale() . '.js')) }}"></script>
    <script src="{{ asset(mix('/js/manifest.js')) }}"></script>
    <script src="{{ asset(mix('/js/vendor.js')) }}"></script>
    <script src="{{ asset(mix('/js/app.js')) }}"></script>
@endpush
