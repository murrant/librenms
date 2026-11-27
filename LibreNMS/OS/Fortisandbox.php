<?php

namespace LibreNMS\OS;

class Fortisandbox extends Generic
{
    protected function pollIfMibRows(array $knownPorts, bool $selected, array $extraOids): array
    {
        $ports = parent::pollIfMibRows($knownPorts, $selected, $extraOids);
        foreach ($ports as &$port) {
            if (($port['ifHighSpeed'] ?? 0) > 2147483647) {
                unset($port['ifHighSpeed']);
            }
        }

        return $ports;
    }
}
