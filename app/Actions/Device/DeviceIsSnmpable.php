<?php

namespace App\Actions\Device;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use SnmpQuery;

class DeviceIsSnmpable
{
    public function execute(Device $device): bool
    {
        $oid = LibrenmsConfig::getOsSetting($device->os, 'snmp.check_oid', 'SNMPv2-MIB::sysObjectID.0');
        $response = SnmpQuery::device($device)->get($oid);

        return $response->getExitCode() === 0 || $response->isValid();
    }
}
