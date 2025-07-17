<?php

/*
 * LibreNMS
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 *
 * @package    LibreNMS
 * @subpackage webui
 * @link       https://www.librenms.org
 * @copyright  2018 LibreNMS
 * @author     LibreNMS Contributors
*/

namespace LibreNMS\OS;

use App\Models\Processor;
use Illuminate\Support\Str;
use LibreNMS\Interfaces\Discovery\ProcessorDiscovery;
use LibreNMS\OS;

class Sonicwall extends OS implements ProcessorDiscovery
{
    /**
     * Discover processors.
     * Returns an array of LibreNMS\Device\Processor objects that have been discovered
     *
     * @return Collection<Processor>
     */
    public function discoverProcessors(): \Illuminate\Support\Collection
    {
        if (Str::startsWith($this->getDeviceArray()['sysObjectID'], '.1.3.6.1.4.1.8741.1')) {
            return [
                new \App\Models\Processor([
                    'processor_type' => 'sonicwall',
                    'processor_oid' => '.1.3.6.1.4.1.8741.1.3.1.3.0',
                    'processor_index' => 0,
                    'processor_descr' => 'CPU',
                    'processor_precision' => 1,
                    'entPhysicalIndex' => 0,
                    'hrDeviceIndex' => null,
                    'processor_perc_warn' => null,
                    'processor_usage' => null ?? 'FIXME_PROCESSOR_USAGE',
                ]),
            ];
        } else {
            return [
                new \App\Models\Processor([
                    'processor_type' => 'sonicwall',
                    'processor_oid' => $this->getDeviceArray()['sysObjectID'] . '.2.1.3.0',
                    'processor_index' => 0,
                    'processor_descr' => 'CPU',
                    'processor_precision' => 1,
                    'entPhysicalIndex' => 0,
                    'hrDeviceIndex' => null,
                    'processor_perc_warn' => null,
                    'processor_usage' => null ?? 'FIXME_PROCESSOR_USAGE',
                ]),
            ];
        }
    }
}
