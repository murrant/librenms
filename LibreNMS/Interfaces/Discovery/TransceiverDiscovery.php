<?php
/*
 * TransceiverDiscovery.php
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2024 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Interfaces\Discovery;

use Illuminate\Support\Collection;

interface TransceiverDiscovery
{
    /**
     * Discover transceivers.
     * Distance is in meters.
     *
     * @return Collection<\App\Models\Transceiver>
     */
    public function discoverTransceivers(): Collection;

    /**
     * Discover transceiver DDM metric data.  Previously discovered transceivers are passed in.
     *
     * @param  Collection<\App\Models\Transceiver>  $transceivers
     * @return Collection<\App\Models\TransceiverMetric>
     */
    public function discoverTransceiverMetrics(Collection $transceivers): Collection;
}