<?php

namespace LibreNMS\OS;

class Slms extends Generic
{
    protected function discoverIfMibRows(array $extraOids): array
    {
        $ports = parent::discoverIfMibRows($extraOids);
        $physical = [];
        foreach ($this->portTable('ZHONE-INTERFACE-TRANSLATION-MIB::zhonePhysicalIfIndex', true) as $key => $data) {
            $parts = explode('.', $key);
            $index = $data['zhonePhysicalIfIndex'];
            if (isset($ports[$index], $parts[5])) {
                $physical[$index] = ['ifType' => $parts[4] === 'other' ? $parts[5] : $parts[4]] + $ports[$index];
            }
        }

        return $physical;
    }
}
