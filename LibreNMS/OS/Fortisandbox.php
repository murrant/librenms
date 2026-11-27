<?php

namespace LibreNMS\OS;

class Fortisandbox extends Generic
{
    protected function pollPortMetrics(array &$ports): void
    {
        foreach ($ports as &$port) {
            if (($port['ifHighSpeed'] ?? 0) > 2147483647) {
                unset($port['ifHighSpeed']);
            }
        }
    }
}
