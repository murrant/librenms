<?php
/**
 * DeviceSecretRepository.php
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

use App\Data\Secrets\IpmiSecret;
use App\Data\Secrets\SecretData;
use App\Data\Secrets\SnmpSecret;
use App\Models\Device;
use LibreNMS\Enum\SecretType;

readonly class DeviceSecretRepository
{
    public function __construct(private Device $device)
    {
    }

    /**
     * @template T of SecretData
     * @param  class-string<T>  $secretClass
     * @return T|null
     */
    public function getSecret(string $secretClass): ?SecretData
    {
        $type = SecretType::fromClass($secretClass);
        $data = $this->getRawSecretData($type->value);

        return $data !== null ? $secretClass::fromArray($data) : null;
    }

    public function ipmi(): ?IpmiSecret
    {
        return $this->getSecret(IpmiSecret::class);
    }

    public function snmp(): ?SnmpSecret
    {
        return $this->getSecret(SnmpSecret::class);
    }

    private function getRawSecretData(string $type): ?array
    {
        return $this->device->secrets->firstWhere('secret_type', $type)?->data;
    }
}
