<?php

namespace LibreNMS\OS;

class Luminato extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        foreach ($ports as &$port) {
            if (empty($port['ifOperStatus']) && isset($port['ifConnectorPresent'])) {
                $port['ifOperStatus'] = $port['ifConnectorPresent'] === 'true' ? 'up' : 'down';
            }
            if (isset($port['ifSpeed'])) {
                if ((int) $this->getDevice()->version >= 20) {
                    $port['ifHighSpeed'] = $port['ifSpeed'];
                } else {
                    unset($port['ifHighSpeed']);
                }
            }
        }

        return $ports;
    }
}
