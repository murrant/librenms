@extends('layouts.install')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-2">
                <div id="db-form-header" class="card-header h6">
                    @lang('install.discover.snmp') <i class="fa fa-question-circle" data-toggle="tooltip" title="@lang('install.discover.snmp_help')"></i>
                </div>
                <div id="db-form-container" class="card-body collapse show">
                    <form id="database-form" class="form-horizontal" role="form" method="post" action="{{ route('discover.action.scan') }}">
                        @csrf
                        <div class="form-row pb-3">
                            <label for="community" class="col-4 col-form-label text-right">@lang('install.discover.community')</label>
                            <div class="col-8">
                                @foreach($snmp['community'] as $index => $community)
                                    <input type="text" class="form-control" name="community[{{ $index }}]" id="community-{{ $index }}" value="{{ $community }}" placeholder="@lang('install.discover.community_placeholder')">
                                @endforeach
                            </div>
                        </div>
                        <div>
                                    <div class="panel panel-default">
                                        <div class="panel-heading">
                                            <h3 class="panel-title">@{{ id+1 }}. <span class="pull-right text-danger" @click="removeItem(id)" v-if="!disabled"><i class="fa fa-minus-circle"></i></span></h3>
                                        </div>
                                        <div class="panel-body">
                                            <form @onsubmit.prevent>
                                                <div class="form-group">
                                                    <div class="col-sm-12">
                                                        <select class="form-control" id="authlevel" v-model="item.authlevel" :disabled="disabled" @change="updateItem(id, $event.target.id, $event.target.value)">
                                                            <option value="noAuthNoPriv" v-text="$t('settings.settings.snmp.v3.level.noAuthNoPriv')"></option>
                                                            <option value="authNoPriv" v-text="$t('settings.settings.snmp.v3.level.authNoPriv')"></option>
                                                            <option value="authPriv" v-text="$t('settings.settings.snmp.v3.level.authPriv')"></option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <fieldset name="algo" v-show="item.authlevel.toString().substring(0, 4) === 'auth'" :disabled="disabled">
                                                    <legend class="h4" v-text="$t('settings.settings.snmp.v3.auth')"></legend>
                                                    <div class="form-group">
                                                        <label for="authalgo" class="col-sm-3 control-label" v-text="$t('settings.settings.snmp.v3.fields.authalgo')"></label>
                                                        <div class="col-sm-9">
                                                            <select class="form-control" id="authalgo" name="authalgo" v-model="item.authalgo" @change="updateItem(id, $event.target.id, $event.target.value)">
                                                                <option value="MD5">MD5</option>
                                                                <option value="SHA">SHA</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="authname" class="col-sm-3 control-label" v-text="$t('settings.settings.snmp.v3.fields.authname')"></label>
                                                        <div class="col-sm-9">
                                                            <input type="text" class="form-control" id="authname" :value="item.authname" @input="updateItem(id, $event.target.id, $event.target.value)">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="authpass" class="col-sm-3 control-label" v-text="$t('settings.settings.snmp.v3.fields.authpass')"></label>
                                                        <div class="col-sm-9">
                                                            <input type="text" class="form-control" id="authpass" :value="item.authpass" @input="updateItem(id, $event.target.id, $event.target.value)">
                                                        </div>
                                                    </div>
                                                </fieldset>

                                                <fieldset name="crypt" v-show="item.authlevel === 'authPriv'" :disabled="disabled">
                                                    <legend class="h4" v-text="$t('settings.settings.snmp.v3.crypto')"></legend>
                                                    <div class="form-group">
                                                        <label for="cryptoalgo" class="col-sm-3 control-label">Cryptoalgo</label>
                                                        <div class="col-sm-9">
                                                            <select class="form-control" id="cryptoalgo" v-model="item.cryptoalgo" @change="updateItem(id, $event.target.id, $event.target.value)">
                                                                <option value="AES">AES</option>
                                                                <option value="DES">DES</option>
                                                            </select>
                                                        </div>

                                                    </div>
                                                    <div class="form-group">
                                                        <label for="cryptopass" class="col-sm-3 control-label" v-text="$t('settings.settings.snmp.v3.fields.authpass')"></label>
                                                        <div class="col-sm-9">
                                                            <input type="text" class="form-control" id="cryptopass" :value="item.cryptopass"  @input="updateItem(id, $event.target.id, $event.target.value)">
                                                        </div>
                                                    </div>
                                                </fieldset>
                                            </form>
                                        </div>
                                    </div>
                            <div class="row snmp3-add-button" v-if="!disabled">
                                <div class="col-sm-12">
                                    <button class="btn btn-primary" @click="addItem()"><i class="fa fa-plus-circle"></i> @{{ $t('New') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card mb-2">
                <div id="db-form-header" class="card-header h6">
                    @lang('install.discover.description')
                </div>
                <div id="db-form-container" class="card-body collapse show">
                    <form id="database-form" class="form-horizontal" role="form" method="post" action="{{ route('discover.action.scan') }}">
                        @csrf
                        <div class="form-row pb-3">
                            <label for="networks" class="col-4 col-form-label text-right">@lang('install.discover.networks')</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="networks" id="networks" value="{{ implode(';', $networks ?? []) }}" placeholder="@lang('install.discover.networks_placeholder')">
                            </div>
                        </div>
                        <div class="form-row pb-3">
                            <label for="domain" class="col-4 col-form-label text-right">@lang('install.discover.domain')</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="domain" id="domain" value="{{ $domain ?? '' }}" placeholder="@lang('install.discover.domain_placeholder')">
                            </div>
                        </div>
                        <div class="form-row pb-3">
                            <label for="localhost" class="col-4 col-form-label text-right">@lang('install.discover.localhost')</label>
                            <div class="col-8">
                                <input type="checkbox" name="localhost" id="localhost" @if($localhost) checked @endif>
                            </div>
                        </div>
                        <div class="form-row pb-3">
                            <label for="ping" class="col-4 col-form-label text-right">@lang('install.discover.ping')</label>
                            <div class="col-8">
                                <input type="checkbox" name="ping" id="ping" @if($ping) checked @endif>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        $("#ping").bootstrapSwitch({onSwitchChange: (event, state) => ! state || confirm('@lang('install.discover.ping_tip')')})
        $("#localhost").bootstrapSwitch();
        $("[data-toggle='tooltip']").tooltip();
    </script>
@endsection
