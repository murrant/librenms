<?php

namespace LibreNMS\OS;

class Zebra extends Generic
{
    protected function discoverIfMibRows(array $extraOids): array
    {
        $ports = parent::discoverIfMibRows($extraOids);
        foreach ($ports as &$port) {
            $port['ifSpeed'] = 100000000;
            unset($port['ifHighSpeed']);
        }

        return $ports;
    }
}
