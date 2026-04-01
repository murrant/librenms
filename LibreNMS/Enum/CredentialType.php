<?php
/**
 * CredentialType.php
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

namespace LibreNMS\Enum;

use App\Data\Credentials\CredentialData;
use App\Data\Credentials\IpmiCredentialData;
use App\Data\Credentials\SnmpCredentialData;

enum CredentialType: string
{
    case Snmp = 'snmp';
    case Ipmi = 'ipmi';

    /** @param class-string<CredentialData> $class */
    public static function fromClass(string $class): self
    {
        foreach (self::cases() as $case) {
            if ($case->credentialClass() === $class) {
                return $case;
            }
        }

        throw new \InvalidArgumentException("Unregistered credential class: $class");
    }

    /** @return class-string<CredentialData> */
    public function credentialClass(): string
    {
        return match($this) {
            self::Snmp => SnmpCredentialData::class,
            self::Ipmi => IpmiCredentialData::class,
        };
    }
}
