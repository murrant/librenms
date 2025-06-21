<?php

/**
 * Arbos.php
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
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\OS;

use LibreNMS\Data\Definitions\FieldValue;
use LibreNMS\Data\Definitions\StorageType;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\Interfaces\Polling\OSPolling;
use LibreNMS\OS;
use SnmpQuery;

class Arbos extends OS implements OSPolling
{
    public function pollOS(DataStorageInterface $datastore): void
    {
        $flows = SnmpQuery::get([
            'PEAKFLOW-SP-MIB::deviceTotalFlowsHC.0',
            'PEAKFLOW-SP-MIB::deviceTotalFlows.0',
        ])->value();

        if (is_numeric($flows)) {
            $datastore->write('arbos_flows', [
                'flows' => FieldValue::asInt($flows, StorageType::COUNTER),
            ]);

            $this->enableGraph('arbos_flows');
        }
    }
}
