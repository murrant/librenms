<?php

namespace LibreNMS\OS;

class Advantech extends Generic
{
    protected function discoverPortMetadata(array &$ports): void
    {
        foreach ($this->portTable('ADVANTECH-EKI-PRONEER-MIB::description') as $index => $values) {
            if (isset($ports[$index])) {
                $ports[$index] = $this->mapPortFields($values, ['ifAlias' => 'description']) + $ports[$index];
            }
        }
    }
}
