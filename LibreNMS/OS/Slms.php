<?php

namespace LibreNMS\OS;

class Slms extends Generic
{
    protected function discoverPortMetadata(array &$ports): void
    {
        $physical = [];
        foreach ($this->portTable('ZHONE-INTERFACE-TRANSLATION-MIB::zhonePhysicalIfIndex', true) as $key => $data) {
            $parts = explode('.', $key);
            $index = $data['zhonePhysicalIfIndex'];
            if (isset($ports[$index], $parts[5])) {
                $physical[$index] = ['ifType' => $parts[4] === 'other' ? $parts[5] : $parts[4]] + $ports[$index];
            }
        }
        $ports = $physical;
    }
}
