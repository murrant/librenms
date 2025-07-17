<?php

/**
 * Fs-switch.php
 *
 * -Description-
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
 * @copyright  2019 PipoCanaja
 * @author     PipoCanaja <pipocanaja@gmail.com>
 */

namespace LibreNMS\OS;

use App\Models\Processor;
use LibreNMS\OS;

class FsSwitch extends OS
{
    public static function normalizeTransceiverValues($value): float
    {
        // Convert fixed-point integer thresholds to float
        $type = gettype($value);
        if ($type === 'integer') {
            // Thresholds are integers
            $value /= 100.0;
        }

        return $value;
    }

    public static function normalizeTransceiverValuesCurrent($value): float
    {
        $value = FsSwitch::normalizeTransceiverValues($value);
        $value *= 0.001; // mA to A

        return $value;
    }

    /**
     * Discover processors.
     * Returns an array of LibreNMS\Device\Processor objects that have been discovered
     *
     * @return Collection<Processor>
     */
    public function discoverProcessors(): \Illuminate\Support\Collection
    {
        $processors = [];

        // Tests OID from SWITCH MIB.
        $processors_data = snmpwalk_cache_oid($this->getDeviceArray(), 'ssCpuIdle', [], 'SWITCH', 'fs');

        foreach ($processors_data as $index => $entry) {
            $processors[] = new \App\Models\Processor([
                'processor_type' => 'fs-SWITCHMIB',
                'processor_oid' => '.1.3.6.1.4.1.27975.1.2.11.' . $index,
                'processor_index' => $index,
                'processor_descr' => 'CPU',
                'processor_precision' => -1,
                'entPhysicalIndex' => 0,
                'hrDeviceIndex' => null,
                'processor_perc_warn' => null,
                'processor_usage' => 100 - $entry['ssCpuIdle'] ?? 'FIXME_PROCESSOR_USAGE',
            ]);
        }

        return collect($processors);
    }
}
