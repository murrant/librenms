<?php

namespace LibreNMS\OS;

class VmwareVcsa extends Shared\Unix
{
    protected function discoverPortMetadata(array &$ports): void
    {
        foreach ($ports as &$port) {
            if (preg_match('/Device ([a-z0-9]+) at /', $port['ifDescr'] ?? '', $match)) {
                $port['ifName'] = $match[1];
            }
        }
    }
}
