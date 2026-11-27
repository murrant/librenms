<?php

namespace LibreNMS\OS;

class Ecosdn extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        if ($knownPorts !== null && $this->portAssociationMode() !== 'ifAlias') {
            return $ports;
        }

        $aliases = \SnmpQuery::numeric()->walk('.1.3.6.1.4.1.45555.3.2.1.10')->throwIfConnectionError()->values();
        foreach ($ports as $index => &$port) {
            $oid = ".1.3.6.1.4.1.45555.3.2.1.10.$index";
            if (isset($aliases[$oid])) {
                $port['ifAlias'] = $aliases[$oid];
            } else {
                $port['keepStoredAlias'] = true;
            }
        }

        return $ports;
    }
}
