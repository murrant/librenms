@extends('layouts.librenmsv1')

@section('title', __('Add Device'))

@section('content')
    <div class="tw-max-w-screen-lg tw-mx-auto tw-px-5" x-data='{
    advanced: @json($advanced),
    port_association: @json($port_association),
    default_poller_group: @json($default_poller_group),
    type: "v2c",
    test: "testing",
    buttons: {v1: "SNMP v1", v2c: "SNMP v2c", v3: "SNMP v3", ping: "PING"}
}'>
        <x-panel class="">
            <x-slot name="title">
                <div class="tw-flex tw-justify-between">
                <div>
                    @lang('Add Device')
                </div>
                <div>
                    <x-toggle x-model="advanced"></x-toggle>
                    @lang('Advanced')
                </div>
                </div>
            </x-slot>
            <div x-text="(advanced ? 'advanced' : 'simple')"></div>
                <x-radio-button-group x-model="type"></x-radio-button-group>
            <ul>
{{--                <li x-for="(value, key) in data" x-text="key + ': ' + value"></li>--}}
            </ul>
        </x-panel>
    </div>
    <div class="container">
        <div id="app">
            <add-device :data="{{ json_encode(compact('advanced', 'port_association', 'default_poller_group')) }}" inline-template>
                <div class="row">
                    <div class="col-md-offset-1 col-md-10">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <div class="panel-title">@lang('Add Device')
                                    <div class="pull-right checkbox-inline">
                                        <label>
                                            <toggle-button name="@lang('Advanced')" :value="advanced" :sync="true" @change="toggleAdvanced"></toggle-button> @lang('Advanced')
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
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced" @if(!$advanced) style="display:none" @endif>
                                        <label for="override_ip" class="col-sm-3 control-label">@lang('Override IP')</label>
                                        <div class="col-sm-9">
                                            <div class="input-group">
                                                <input type="text" class="form-control input-sm" id="override_ip" placeholder="IP" v-model="override_ip">
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                    </div>

                                    @config('distributed_poller')
                                    <div class="form-group" v-show="advanced" @if(!$advanced) style="display:none" @endif>
                                        <label for="poller_group" class="col-sm-3 control-label">@lang('Poller Group')</label>
                                        <div class="col-sm-9">
                                            <div class="input-group">
                                                <select name="poller_group" id="poller_group" class="form-control input-sm" v-model.number="poller_group">
                                                    <option value="0"> Default poller group</option>
                                                    @foreach($poller_groups as $id => $group)
                                                        <option value="{{ $id }}" @if($id == $default_poller_group) selected @endif>{{ $group }}</option>
                                                    @endforeach
                                                </select>
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                    @endconfig

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
                                            <div class="input-group">
                                                <input type="text" class="form-control input-sm" id="community" placeholder="@lang('Leave blank to use default')" v-model="community">
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-sm-3">
                                            <a href="{{ route('settings') . '/poller/snmp' }}">
                                                <button type="button" class="btn btn-default btn-sm">@lang('Configure Default')</button>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- SNMP v3 Options -->

                                    <div class="form-group" v-show="type === 'snmpv3'" style="display:none">
                                        <label for="auth_level" class="col-sm-3 control-label">@lang('Authentication')</label>
                                        <div class="col-sm-9">
                                            <button-group class="btn-group btn-group-sm" v-model="auth_level">
                                                <button type="button" class="btn btn-sm btn-default active" value="noAuthNoPriv">@lang('None')</button>
                                                <button type="button" class="btn btn-sm btn-default" value="authNoPriv">@lang('Password')</button>
                                                <button type="button" class="btn btn-sm btn-default" value="authPriv">@lang('Encrypted')</button>
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
                                            <input type="text" placeholder="@lang('Password')" id="auth_pass" class="form-control input-sm" autocomplete="off" v-model="auth_pass">
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
                                            <input type="text" placeholder="@lang('Crypto Password')" id="crypto_pass" class="form-control input-sm" autocomplete="off"
                                                   v-model="crypto_pass">
                                        </div>
                                        <div class="col-sm-3" v-show="advanced">
                                            <button-group class="btn-group btn-group-sm" v-model="crypto_algo" title="@lang('Crypto Algorithm')">
                                                <button type="button" class="btn btn-sm btn-default active" value="AES">AES</button>
                                                <button type="button" class="btn btn-sm btn-default" value="DES">DES</button>
                                            </button-group>
                                        </div>
                                    </div>

                                    <!-- SNMP Common Options -->

                                    <div class="form-group" v-show="advanced && type !== 'ping'" @if(!$advanced) style="display:none" @endif>
                                        <label class="col-sm-3 control-label">@lang('Transport')</label>

                                        <div class="col-sm-3">
                                            <label class="sr-only" for="port">@lang('Port')</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control input-sm" id="port" placeholder="@lang('Port')" v-model="port">
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-sm-3">
                                            <label for="ipt" class="sr-only">@lang('IP')</label>
                                            <button-group class="btn-group btn-group-sm" v-model="transport">
                                                <button type="button" class="btn btn-sm btn-default active" value="auto">@lang('Auto')</button>
                                                <button type="button" class="btn btn-sm btn-default" value="4">IPv4</button>
                                                <button type="button" class="btn btn-sm btn-default" value="6">IPv6</button>
                                            </button-group>
                                        </div>
                                        <div class="col-sm-3">
                                            <label for="proto" class="sr-only">@lang('Protocol')</label>
                                            <button-group class="btn-group btn-group-sm" v-model="proto">
                                                <button type="button" class="btn btn-sm btn-default active" value="udp">UDP</button>
                                                <button type="button" class="btn btn-sm btn-default" value="tcp">TCP</button>
                                            </button-group>
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type !== 'ping'" @if(!$advanced) style="display:none" @endif>
                                        <label for="port_association" class="col-sm-3 control-label">Port Association</label>
                                        <div class="col-sm-3">
                                            <div class="input-group">
                                                <select name="port_association" id="port_association" class="form-control input-sm" v-model="port_association">
                                                    @foreach($port_association_modes as $mode)
                                                        <option value="{{ $mode }}">{{ $mode }}</option>
                                                    @endforeach
                                                </select>
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ping Options -->

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="sysName" class="col-sm-3 control-label">@lang('sysName')</label>
                                        <div class="col-sm-9">
                                            <div class="input-group">
                                                <input type="text" class="form-control input-sm" id="sysName" placeholder="@lang('sysName')" v-model="sysname">
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="os" class="col-sm-3 control-label">@lang('OS')</label>
                                        <div class="col-sm-9">
                                            <div class="input-group">
                                                <input type="text" class="form-control input-sm" id="os" placeholder="@lang('OS')" v-model="os">
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group" v-show="advanced && type === 'ping'" style="display:none">
                                        <label for="hardware" class="col-sm-3 control-label">@lang('Hardware')</label>
                                        <div class="col-sm-9">
                                            <div class="input-group">
                                                <input type="text" class="form-control input-sm" id="hardware" placeholder="@lang('Hardware')" v-model="hardware">
                                                <span class="input-group-addon"><i class="fa fa-fw fa-lg fa-question-circle has-tooltip"></i></span>
                                            </div>
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
                    <div class="col-md-offset-1 col-md-10">
                        <div :class="['alert', resultStatusClass(result.status)]" v-for="result in results" style="display: none" v-show="results.length > 0">
                            <h4 v-if="result.status === 'success'"><a :href="'{{ url('/') . '/device/device=' }}' + result.device_id">@{{ result.hostname }} <span v-if="result.status === 'canceled'">@lang('Canceled')</span></a></h4>
                            <h4 v-else>@{{ result.hostname }}</h4>
                            <div class="row">
                                <div class="col-sm-12">
                                    <i v-if="result.status === 'pending'" class="fa fa-spinner fa-spin fa-3x fa-fw"></i>
                                    <span class="pull-right">
                                        <button v-if="result.status === 'failed' || result.status === 'canceled'" type="button" class="btn btn-sm btn-default" @click="restoreFormState(result.data)">@lang('Edit')</button>
                                        <button v-if="result.status === 'failed'" type="button" class="btn btn-sm btn-default">@lang('Force Add')</button>
                                        <button v-if="result.status === 'pending'" type="button" class="btn btn-sm btn-default" @click="result.status = 'canceled'">@lang('Cancel')</button>
                                    </span>
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
    <script src="{{ asset(mix('/js/lang/' . app()->getLocale() . '.js')) }}"></script>
    <script src="{{ asset(mix('/js/manifest.js')) }}"></script>
    <script src="{{ asset(mix('/js/vendor.js')) }}"></script>
    <script src="{{ asset(mix('/js/app.js')) }}"></script>
@endpush
