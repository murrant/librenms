<?php

/**
 * Aos.php
 *
 * Alcatel-Lucent AOS
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2017 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\OS;

use App\Models\Processor;
use Barryvdh\Reflection\DocBlock\Type\Collection;
use LibreNMS\Interfaces\Discovery\ProcessorDiscovery;
use LibreNMS\OS;
use SnmpQuery;

class Aos extends OS implements ProcessorDiscovery
{
    /**
     * Discover processors.
     * Returns an array of LibreNMS\Device\Processor objects that have been discovered
     *
     * @return Collection<Processor>
     */
    public function discoverProcessors(): \Illuminate\Support\Collection
    {
        $usage = SnmpQuery::get('ALCATEL-IND1-HEALTH-MIB::healthDeviceCpuLatest.0')->value();
        if (is_numeric($usage)) {
            return collect([new Processor([
                'processor_type' => 'aos-system',
                'processor_oid' => '.1.3.6.1.4.1.6486.800.1.2.1.16.1.1.1.13.0',
                'processor_index' => 0,
                'processor_descr' => 'Device CPU',
                'processor_precision' => 1,
                'entPhysicalIndex' => 0,
                'hrDeviceIndex' => null,
                'processor_perc_warn' => null,
                'processor_usage' => $usage,
            ])]);
        }

        $usage = SnmpQuery::get('ALCATEL-IND1-HEALTH-MIB::healthModuleCpu1MinAvg.0')->value();
        if (is_numeric($usage)) {
            return collect([new Processor([
                'processor_type' => 'aos-system',
                'processor_oid' => '.1.3.6.1.4.1.6486.801.1.2.1.16.1.1.1.1.1.11.0',
                'processor_index' => 0,
                'processor_descr' => 'Device CPU',
                'processor_precision' => 1,
                'entPhysicalIndex' => 0,
                'hrDeviceIndex' => null,
                'processor_perc_warn' => null,
                'processor_usage' => $usage,
            ])]);
        }

        return new Collection;
    }
}
