<?php
/**
 * SnmpV3CredentialType.php
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

namespace LibreNMS\Credentials;

class SnmpV3CredentialType extends CredentialType
{
    public function name(): string
    {
        return 'SNMP v3';
    }

    public function validate(array $data): bool
    {
        return isset($data['authname']) && is_string($data['authname']);
    }

    public function schema(): array
    {
        return [
            'authname' => ['type' => 'string', 'label' => 'Auth Name', 'required' => true],
            'authlevel' => ['type' => 'enum', 'options' => ['noAuthNoPriv', 'authNoPriv', 'authPriv'], 'label' => 'Auth Level'],
            'authalgo' => ['type' => 'enum', 'options' => ['MD5', 'SHA', 'SHA-224', 'SHA-256', 'SHA-384', 'SHA-512'], 'label' => 'Auth Algo'],
            'authpass' => ['type' => 'string', 'label' => 'Auth Pass', 'secret' => true],
            'cryptoalgo' => ['type' => 'enum', 'options' => ['DES', 'AES', 'AES-192', 'AES-256', 'AES-192-C', 'AES-256-C'], 'label' => 'Crypto Algo'],
            'cryptopass' => ['type' => 'string', 'label' => 'Crypto Pass', 'secret' => true],
        ];
    }

    public function renderUi(): string
    {
        return 'snmp-v3-ui'; // Placeholder for frontend component
    }

    public function parse(array $data): array
    {
        return [
            'authname' => (string) ($data['authname'] ?? ''),
            'authlevel' => (string) ($data['authlevel'] ?? 'noAuthNoPriv'),
            'authalgo' => (string) ($data['authalgo'] ?? 'SHA'),
            'authpass' => (string) ($data['authpass'] ?? ''),
            'cryptoalgo' => (string) ($data['cryptoalgo'] ?? 'AES'),
            'cryptopass' => (string) ($data['cryptopass'] ?? ''),
        ];
    }
}
