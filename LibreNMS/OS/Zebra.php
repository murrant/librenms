<?php

namespace LibreNMS\OS;

class Zebra extends Generic
{
    protected function pollIfMibRows(array $knownPorts, bool $selected, array $extraOids): array
    {
        $ports = parent::pollIfMibRows($knownPorts, $selected, $extraOids);
        foreach ($ports as &$port) {
            $port['ifSpeed'] = 100000000;
            unset($port['ifHighSpeed']);
        }

        return $ports;
    }
}
