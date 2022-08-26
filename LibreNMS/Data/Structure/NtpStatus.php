<?php
/*
 * NtpStatus.php
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

namespace LibreNMS\Data\Structure;

use Illuminate\Support\Collection;

class NtpStatus
{
    /** @var int */
    public $stratum;

    /** @var \Illuminate\Support\Collection<\LibreNMS\Data\Structure\NtpPeer> */
    public $peers;

    public function __construct(int $stratum = -1, ?Collection $peers = null)
    {
        $this->stratum = $stratum;
        $this->peers = $peers ?? new Collection;
    }

    public function addPeer(NtpPeer $peer)
    {
        $this->peers->push($peer);
    }
}
