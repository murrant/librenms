<?php

namespace LibreNMS\OS\Shared;

use LibreNMS\Device\Processor;
use LibreNMS\Interfaces\Discovery\ProcessorDiscovery;
use LibreNMS\OS;

class Radlan extends OS implements ProcessorDiscovery
{
    public function discoverProcessors(): array
    {
        $usage = \SnmpQuery::mibDir('radlan')->get('RADLAN-rndMng::rlCpuUtilDuringLastMinute.0')->value();

        if ($usage !== '' && $usage !== '101') {
            return [
                Processor::discover(
                    type: 'radlan',
                    device_id: $this->getDeviceId(),
                    oid: '.1.3.6.1.4.1.89.1.8.0',
                    index: 0,
                    current_usage: (int) $usage,
                ),
            ];
        }

        return [];
    }

    /**
     * Temperature OID
     *
     * .1.3.6.1.4.1.89.53.15.1.9
     * .1.3.6.1.4.1.89.53.15.1.10
     *
     * CPU OIDs
     *
     * .1.3.6.1.4.1.89.1.6
     * .1.3.6.1.4.1.89.1.7
     * .1.3.6.1.4.1.89.1.8
     * .1.3.6.1.4.1.89.1.9
     *
     * Memory OIDs
     *
     * .1.3.6.1.4.1.89.29.11.1
     * .1.3.6.1.4.1.89.29.11.2
     *
     * P.S. OIDs
     *
     * .1.3.6.1.4.1.89.35.5.1.1.2
     * .1.3.6.1.4.1.89.83.1.2.1.2
     * .1.3.6.1.4.1.89.83.1.2.1.3
     * .1.3.6.1.4.1.89.83.1.2.1.4
     * .1.3.6.1.4.1.89.53.15.1.3
     * .1.3.6.1.4.1.89.53.15.1.3
     *
     * Fan OIDs
     *
     * .1.3.6.1.4.1.89.83.1.1.1.2
     * .1.3.6.1.4.1.89.83.1.1.1.3
     * .1.3.6.1.4.1.89.53.15.1.4
     * .1.3.6.1.4.1.89.53.15.1.5
     * .1.3.6.1.4.1.89.53.15.1.6
     * .1.3.6.1.4.1.89.53.15.1.7
     * .1.3.6.1.4.1.89.53.15.1.8
     */
}
