<?php
/**
 * DeviceCredentialRepository.php
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
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Data;

use App\Data\Credentials\CredentialData;
use App\Data\Credentials\IpmiCredentialData;
use App\Data\Credentials\SnmpCredentialData;
use App\Models\Device;
use LibreNMS\Enum\CredentialType;

readonly class DeviceCredentialRepository
{
    public function __construct(private Device $device)
    {
    }

    /**
     * @template T of CredentialData
     * @param  class-string<T>  $credentialClass
     * @return T|null
     */
    public function getCredential(string $credentialClass): ?CredentialData
    {
        $type = CredentialType::fromClass($credentialClass);
        $data = $this->getRawCredentialData($type->value);

        return $data !== null ? $credentialClass::fromArray($data) : null;
    }

    public function ipmi(): ?IpmiCredentialData
    {
        return $this->getCredential(IpmiCredentialData::class);
    }

    public function snmp(): ?SnmpCredentialData
    {
        return $this->getCredential(SnmpCredentialData::class);
    }

    private function getRawCredentialData(string $type): ?array
    {
        return $this->device->credentials->firstWhere('credential_type', $type)?->data;
    }
}
