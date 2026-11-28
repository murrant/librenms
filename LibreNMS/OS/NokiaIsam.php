<?php

namespace LibreNMS\OS;

use LibreNMS\Ports\PortInventory;

class NokiaIsam extends Generic
{
    public function discoverPorts(): PortInventory
    {
        $ports = parent::collectPortRows(null, false);
        $complete = $this->addIhubPorts($ports, ['IF-MIB::ifDescr', 'IF-MIB::ifName', 'IF-MIB::ifAlias', 'IF-MIB::ifType', 'IF-MIB::ifOperStatus', 'IF-MIB::ifSpeed', 'IF-MIB::ifHighSpeed']);

        return new PortInventory($this->withCustomerAliases($ports), $complete, $this->portAssociationMode());
    }

    private function withCustomerAliases(array $ports): array
    {
        foreach ($this->portTable('ITF-MIB-EXT::asamIfExtCustomerId') as $index => $row) {
            if (isset($ports[$index])) {
                $ports[$index]['ifAlias'] = $row['asamIfExtCustomerId'];
            }
        }

        return $ports;
    }

    private function addIhubPorts(array &$ports, array $oids): bool
    {
        // Some ISAMs expose no ihub context. Its absence must not retire previously seen ports.
        $context = \SnmpQuery::context('ihub')->hideMib()->enumStrings()->walk($oids);
        if ($context->hasConnectionError() || $context->exitCode !== 0) {
            return false;
        }
        $context->valuesByIndex($ports);

        return true;
    }

    protected function pollIfMibRows(array $knownPorts, bool $selected, array $extraOids): array
    {
        $ports = parent::pollIfMibRows($knownPorts, $selected, $extraOids);
        $this->addIhubPorts($ports, $this->ifMibOids([...$this->portIdentityColumns(), ...$this->portMetricColumns()]));
        if ($this->portAssociationMode() === 'ifAlias') {
            $ports = $this->withCustomerAliases($ports);
        }
        foreach ($ports as &$port) {
            foreach ($port as $field => &$value) {
                if (str_starts_with($field, 'ifHC') && preg_match('/^(?:[0-9a-f]{2} )+[0-9a-f]{2}$/i', $value)) {
                    $value = (string) \Brick\Math\BigInteger::fromBase(str_replace(' ', '', $value), 16);
                }
            }
        }

        return $ports;
    }
}
