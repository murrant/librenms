<?php

namespace LibreNMS\OS;

class Cnmatrix extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        if ($knownPorts !== null && $this->portAssociationMode() !== 'ifAlias') {
            return $ports;
        }

        foreach ($this->portTable('ARICENT-CFA-MIB::ifMainDesc') as $index => $values) {
            if (isset($ports[$index], $values['ifMainDesc'])) {
                $ports[$index]['ifAlias'] = $values['ifMainDesc'];
            }
        }

        return $ports;
    }
}
