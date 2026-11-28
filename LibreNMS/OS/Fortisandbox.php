<?php

namespace LibreNMS\OS;

class Fortisandbox extends Generic
{
    protected function discoverIfMibRows(array $extraOids): array
    {
        $ports = parent::discoverIfMibRows($extraOids);
        foreach ($ports as &$port) {
            if (($port['ifHighSpeed'] ?? 0) > 2147483647) {
                unset($port['ifHighSpeed']);
            }
        }

        return $ports;
    }
}
