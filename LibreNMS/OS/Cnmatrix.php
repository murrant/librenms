<?php

namespace LibreNMS\OS;

class Cnmatrix extends Generic
{
    protected function discoverPortMetadata(array &$ports): void
    {
        foreach ($this->portTable('ARICENT-CFA-MIB::ifMainDesc') as $index => $values) {
            if (isset($ports[$index])) {
                $ports[$index] = $this->mapPortFields($values, ['ifAlias' => 'ifMainDesc']) + $ports[$index];
            }
        }
    }
}
