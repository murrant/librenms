<?php

namespace LibreNMS\OS;

class Zebra extends Generic
{
    protected function pollPortMetrics(array &$ports): void
    {
        foreach ($ports as &$port) {
            $port['ifSpeed'] = 100000000;
            unset($port['ifHighSpeed']);
        }
    }
}
