<?php

namespace LibreNMS\OS;

class AdvaFsp150cp extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        if ($knownPorts !== null && $this->portAssociationMode() === 'ifIndex') {
            return $ports;
        }

        foreach ($this->portTable(['FSP150-MIB::fsp150IfConfigUserString', 'ENTITY-MIB::entPhysicalName']) as $index => $values) {
            if (isset($ports[$index])) {
                $ports[$index] = $this->mapPortFields($values, ['ifAlias' => 'fsp150IfConfigUserString', 'ifName' => 'entPhysicalName']) + $ports[$index];
            }
        }

        return $ports;
    }
}
