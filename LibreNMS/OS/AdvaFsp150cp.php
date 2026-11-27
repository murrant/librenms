<?php

namespace LibreNMS\OS;

class AdvaFsp150cp extends Generic
{
    protected function discoverPortMetadata(array &$ports): void
    {
        foreach ($this->portTable(['FSP150-MIB::fsp150IfConfigUserString', 'ENTITY-MIB::entPhysicalName']) as $index => $values) {
            if (isset($ports[$index])) {
                $ports[$index] = $this->mapPortFields($values, ['ifAlias' => 'fsp150IfConfigUserString', 'ifName' => 'entPhysicalName']) + $ports[$index];
            }
        }
    }
}
