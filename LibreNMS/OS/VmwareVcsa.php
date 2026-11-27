<?php

namespace LibreNMS\OS;

class VmwareVcsa extends Shared\Unix
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        if ($knownPorts !== null && $this->portAssociationMode() === 'ifIndex') {
            return $ports;
        }

        foreach ($ports as &$port) {
            if (preg_match('/Device ([a-z0-9]+) at /', $port['ifDescr'] ?? '', $match)) {
                $port['ifName'] = $match[1];
            }
        }

        return $ports;
    }
}
