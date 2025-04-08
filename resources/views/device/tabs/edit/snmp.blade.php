<form id='edit' name='edit' method='post' action='{{ route('device.edit.snmp', $device) }}' role='form' class='form-horizontal'>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @csrf
    <div class='form-group'>
        <label for='hardware' class='col-sm-2 control-label'>SNMP</label>
        <div class='col-sm-4'>
            <input type='checkbox' id='snmp' name='snmp' data-size='small' onChange='disableSnmp(this);' @if(! $device->snmp_disable)checked @endif>
        </div>
    </div>
    <div id='snmp_override' style='display: {{ $device->snmp_disable ? 'block' : 'none'  }};'>
    <div class='form-group'>
        <label for='sysName' class='col-sm-2 control-label'>sysName (optional)</label>
        <div class='col-sm-4'>
            <input id='sysName' class='form-control' name='sysName' value='{{ $device->sysName }}'/>
        </div>
    </div>
    <div class='form-group'>
        <label for='hardware' class='col-sm-2 control-label'>Hardware (optional)</label>
        <div class='col-sm-4'>
            <input id='hardware' class='form-control' name='hardware' value='{{ $device->hardware }}'/>
        </div>
    </div>
    <div class='form-group'>
        <label for='os' class='col-sm-2 control-label'>OS (optional)</label>
        <div class='col-sm-4'>
            <select id="os" name="os" class="form-control"></select>
        </div>
    </div>
    </div>
    <div id='snmp_conf' style='display: {{ $device->snmp_disable ? 'none' : 'block' }};'>
    <input type=hidden name='editing' value='yes'>
    <div class='form-group'>
        <label for='snmpver' class='col-sm-2 control-label'>SNMP Details</label>
        <div class='col-sm-1'>
            <select id='snmpver' name='snmpver' class='form-control input-sm' onChange='changeForm();'>
                <option value='v1'>v1</option>
                <option value='v2c' @if($device->snmpver === 'v2c')selected @endif>v2c</option>
                <option value='v3' @if($device->snmpver === 'v3')selected @endif>v3</option>
            </select>
        </div>
        <div class='col-sm-2'>
            <input type='number' name='port' placeholder='port' class='form-control input-sm' value='{{ $device->port == LibrenmsConfig::get('snmp.port') ? '' : $device->port }}'>
        </div>
        <div class='col-sm-1'>
            <select name='transport' id='transport' class='form-control input-sm'>
                @foreach(LibrenmsConfig::get('snmp.transports') as $transport)
                    <option value='{{ $transport }}' @if($transport == $device->transport)selected='selected'@endif>{{ $transport }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class='form-group'>
        <div class='col-sm-2'>
        </div>
        <div class='col-sm-1'>
            <input type='number' id='timeout' name='timeout' class='form-control input-sm' value='{{ $device->timeout }}' placeholder='seconds' />
        </div>
        <div class='col-sm-1'>
            <input type='number' id='retries' name='retries' class='form-control input-sm' value='{{ $device->retries }}' placeholder='retries' />
        </div>
    </div>
    <div class='form-group'>
        <label for='port_association_mode' class='col-sm-2 control-label'>Port Association Mode</label>
        <div class='col-sm-1'>
            <select name='port_association_mode' id='port_association_mode' class='form-control input-sm'>
                @foreach(\LibreNMS\Enum\PortAssociationMode::getModes() as $pam_id => $pam)
                    <option value='{{ $pam_id }}' @if($pam_id == $device->port_association_mode)selected='selected'@endif>{{ $pam }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class='form-group'>
        <label for='max_repeaters' class='col-sm-2 control-label'>Max Repeaters</label>
        <div class='col-sm-1'>
            <input type='number' id='max_repeaters' name='max_repeaters' class='form-control input-sm' value='{{ $device->getAttrib('snmp_max_oid') }}' placeholder='max repeaters' />
        </div>
    </div>
    <div class='form-group'>
        <label for='max_oid' class='col-sm-2 control-label'>Max OIDs</label>
        <div class='col-sm-1'>
            <input type='number' id='max_oid' name='max_oid' class='form-control input-sm' value='{{ $device->getAttrib('snmp_max_repeaters') }}' placeholder='max oids' />
        </div>
    </div>
    <div id='snmpv1_2'>
        <div class='form-group'>
            <label class='col-sm-3 control-label text-left'><h4><strong>SNMPv1/v2c Configuration</strong></h4></label>
        </div>
        <div class='form-group'>
            <label for='community' class='col-sm-2 control-label'>SNMP Community</label>
            <div class='col-sm-4'>
                <input type="password" id="community" class="form-control" name="community" value="{{ $device->community }}"
                       onfocus="this.type = 'text'"
                       onblur="this.type = 'password'"
                />
            </div>
        </div>
    </div>
    <div id='snmpv3'>
        <div class='form-group'>
            <label class='col-sm-3 control-label'><h4><strong>SNMPv3 Configuration</strong></h4></label>
        </div>
        <div class='form-group'>
            <label for='authlevel' class='col-sm-2 control-label'>Auth Level</label>
            <div class='col-sm-4'>
                <select id='authlevel' name='authlevel' class='form-control' onchange="handleAuthLevelChange(this)">
                    <option value='noAuthNoPriv'>noAuthNoPriv</option>
                    <option value='authNoPriv' @if($device->authlevel == 'authNoPriv')selected @endif>authNoPriv</option>
                    <option value='authPriv' @if($device->authlevel == 'authPriv' || $device->authlevel === null)selected @endif>authPriv</option>
                </select>
            </div>
        </div>
        <div class='form-group' id="authname-container">
            <label for='authname' class='col-sm-2 control-label'>Auth User Name</label>
            <div class='col-sm-4'>
                <input type='text' id='authname' name='authname' class='form-control' value='{{ $device->authname }}' autocomplete='off'>
            </div>
        </div>
        <div class='form-group' id="authpass-container">
            <label for='authpass' class='col-sm-2 control-label'>Auth Password</label>
            <div class='col-sm-4'>
                <input type='password' id='authpass' name='authpass' class='form-control' value='{{ $device->authpass }}'
                       autocomplete='off'
                       onfocus="this.type = 'text'"
                       onblur="this.type = 'password'"
                />
            </div>
        </div>
        <div class='form-group' id="authalgo-container">
            <label for='authalgo' class='col-sm-2 control-label'>Auth Algorithm</label>
            <div class='col-sm-4'>
                <select id='authalgo' name='authalgo' class='form-control'>
                    @foreach(\LibreNMS\SNMPCapabilities::authAlgorithms() as $algo => $enabled)
                        <option value='{{ $algo }}' @if($device->authalgo === $algo)selected @endif>{{ $algo }}</option>
                    @endforeach
                </select>

                @if(! \LibreNMS\SNMPCapabilities::supportsSHA2())
                <label class="text-left"><small>Some options are disabled. <a href="https://docs.librenms.org/Support/FAQ/#optional-requirements-for-snmpv3-sha2-auth">Read more here</a></small></label>
                @endif
            </div>
        </div>
        <div class='form-group' id="cryptopass-container">
            <label for='cryptopass' class='col-sm-2 control-label'>Crypto Password</label>
            <div class='col-sm-4'>
                <input type='password' id='cryptopass' name='cryptopass' class='form-control' value='{{ $device->cryptopass }}'
                       autocomplete='off'
                       onfocus="this.type = 'text'"
                       onblur="this.type = 'password'"
                />
            </div>
        </div>
        <div class='form-group' id="cryptoalgo-container">
            <label for='cryptoalgo' class='col-sm-2 control-label'>Crypto Algorithm</label>
            <div class='col-sm-4'>
                <select id='cryptoalgo' name='cryptoalgo' class='form-control'>
                    @foreach(\LibreNMS\SNMPCapabilities::cryptoAlgoritms() as $algo => $enabled)
                        <option value='{{ $algo }}' @if($device->cryptoalgo === $algo)selected @endif @if(! $enabled)disabled @endif>{{ $algo }}</option>
                    @endforeach
                </select>

                @if(! \LibreNMS\SNMPCapabilities::supportsAES256())
                <label class="text-left"><small>Some options are disabled. <a href="https://docs.librenms.org/Support/FAQ/#optional-requirements-for-snmpv3-sha2-auth">Read more here</a></small></label>
                @endif
            </div>
        </div>
    </div>

    </div>

    @if(LibrenmsConfig::get('distributed_poller') === true)
        <div class="form-group">
        <label for="poller_group" class="col-sm-2 control-label">Poller Group</label>
        <div class="col-sm-4">
        <select name="poller_group" id="poller_group" class="form-control input-sm">
        <option value="0"> Default poller group</option>
        @foreach($poller_groups as $id => $group)
            <option value="{{ $id }}" @if($device->poller_group == $id)selected @endif>{{ $group }}</option>
        @endforeach
        </select>
        </div>
        </div>
    @endif

    <div class="form-group">
        <label for="force_save" class="control-label col-sm-2">Force Save</label>
        <div class="col-sm-9">
            <input type="checkbox" name="force_save" id="force_save" data-size="small">
        </div>
    </div>

    <div class="row">
        <div class="col-md-1 col-md-offset-2">
            <button type="submit" name="Submit"  class="btn btn-success"><i class="fa fa-check"></i> Save</button>
        </div>
    </div>
</form>

<script>
    $('[name="force_save"]').bootstrapSwitch();

    function changeForm() {
        var snmpVersion = $("#snmpver").val();
        if(snmpVersion === 'v1' || snmpVersion === 'v2c') {
            $('#snmpv1_2').show();
            $('#snmpv3').hide();
        }
        else if(snmpVersion === 'v3') {
            $('#snmpv1_2').hide();
            $('#snmpv3').show();
        }
    }

    function disableSnmp(e) {
        if(e.checked) {
            $('#snmp_conf').show();
            $('#snmp_override').hide();
        } else {
            $('#snmp_conf').hide();
            $('#snmp_override').show();
        }
    }

    function handleAuthLevelChange(e) {
        if (e.value === 'authPriv') {
            $('#authname-container').show();
            $('#authpass-container').show();
            $('#authalgo-container').show();
            $('#cryptopass-container').show();
            $('#cryptoalgo-container').show();
        } else if (e.value === 'authNoPriv') {
            $('#authname-container').show();
            $('#authpass-container').show();
            $('#authalgo-container').show();
            $('#cryptopass-container').hide();
            $('#cryptoalgo-container').hide();
        } else if (e.value === 'noAuthNoPriv') {
            $('#authname-container').hide();
            $('#authpass-container').hide();
            $('#authalgo-container').hide();
            $('#cryptopass-container').hide();
            $('#cryptoalgo-container').hide();
        }
    }

    $("[name='snmp']").bootstrapSwitch('offColor','danger');

    changeForm();


    init_select2('#os', 'os', {}, "{{ $device->os }}");
</script>
