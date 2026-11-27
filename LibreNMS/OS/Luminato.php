<?php

namespace LibreNMS\OS;

class Luminato extends Generic
{
    protected function pollPortMetrics(array &$ports): void
    {
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
    }
}
