<?php

namespace LibreNMS\OS;

class NokiaIsam extends Generic
{
    protected function discoverPortMetadata(array &$ports): void
    {
        $this->addIhubPorts($ports, ['IF-MIB::ifDescr', 'IF-MIB::ifName', 'IF-MIB::ifAlias', 'IF-MIB::ifType', 'IF-MIB::ifOperStatus', 'IF-MIB::ifAdminStatus']);
        foreach ($this->portTable('ITF-MIB-EXT::asamIfExtCustomerId') as $index => $row) {
            if (isset($ports[$index])) {
                $ports[$index]['ifAlias'] = $row['asamIfExtCustomerId'];
            }
        }
    }

    private function addIhubPorts(array &$ports, array $oids): void
    {
        // Some ISAMs expose no ihub context. Its absence must not retire previously seen ports.
        $context = \SnmpQuery::context('ihub')->hideMib()->enumStrings()->walk($oids);
        if ($context->hasConnectionError() || $context->exitCode !== 0) {
            $this->portInventoryComplete = false;
        } else {
            $context->valuesByIndex($ports);
        }
    }

    protected function pollPortMetrics(array &$ports): void
    {
        $this->addIhubPorts($ports, ['IF-MIB::ifTable', 'IF-MIB::ifXTable']);
        foreach ($ports as &$port) {
            foreach ($port as $field => &$value) {
                if (str_starts_with($field, 'ifHC') && preg_match('/^(?:[0-9a-f]{2} )+[0-9a-f]{2}$/i', $value)) {
                    $value = (string) \Brick\Math\BigInteger::fromBase(str_replace(' ', '', $value), 16);
                }
            }
        }
    }
}
