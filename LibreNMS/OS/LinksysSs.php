<?php

namespace LibreNMS\OS;

use App\Facades\LibrenmsConfig;
use LibreNMS\Ports\PortMeasurement;

class LinksysSs extends Generic
{
    protected function pollIfMibRows(array $knownPorts, bool $selected, array $extraOids): array
    {
        $ports = parent::pollIfMibRows($knownPorts, $selected, $extraOids);
        if (! $ports || ! LibrenmsConfig::get('enable_ports_poe')) {
            return $ports;
        }

        foreach ($this->portTable(['LINKSYS-POE-MIB::rlPethPsePortPowerLimit', 'LINKSYS-POE-MIB::rlPethPsePortOutputPower']) as $key => $row) {
            $index = substr(strrchr($key, '.'), 1);
            if (isset($ports[$index], $row['rlPethPsePortOutputPower'])) {
                $limit = $row['rlPethPsePortPowerLimit'] ?? null;
                $ports[$index]['poe'] = PortMeasurement::powerFields($limit, $limit, $row['rlPethPsePortOutputPower'], $limit);
            }
        }

        return $ports;
    }
}
