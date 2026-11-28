<?php

namespace LibreNMS\OS;

class Advantech extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        if ($knownPorts !== null && $this->portAssociationMode() !== 'ifAlias') {
            return $ports;
        }

        foreach ($this->portTable('ADVANTECH-EKI-PRONEER-MIB::description') as $index => $values) {
            if (isset($ports[$index], $values['description'])) {
                $ports[$index]['ifAlias'] = $values['description'];
            }
        }

        return $ports;
    }
}
