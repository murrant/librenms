<?php
/*
 * VrfPortDiscovery.php
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
 * @copyright  2022 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Interfaces;

use Illuminate\Support\Collection;

interface VrfPortDiscovery
{
    /**
     * Discover the vrf ports are connected to and update ifVrf
     *
     * @param  \Illuminate\Support\Collection<\App\Models\Vrf>  $vrfs collection of device vrfs
     * @param  \Illuminate\Support\Collection<\App\Models\Port>  $ports collection of device ports
     * @return \Illuminate\Support\Collection<\App\Models\Port> The updated ports
     */
    public function discoverVrfPorts(Collection $vrfs, Collection $ports): Collection;
}
