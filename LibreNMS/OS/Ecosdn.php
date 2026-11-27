<?php

namespace LibreNMS\OS;

class Ecosdn extends Generic
{
    protected function discoverPortMetadata(array &$ports): void
    {
        $aliases = \SnmpQuery::numeric()->walk('.1.3.6.1.4.1.45555.3.2.1.10')->throwIfConnectionError()->values();
        foreach ($ports as $index => &$port) {
            $oid = ".1.3.6.1.4.1.45555.3.2.1.10.$index";
            if (isset($aliases[$oid])) {
                $port['ifAlias'] = $aliases[$oid];
            } else {
                $port['keepStoredAlias'] = true;
            }
        }
    }
}
