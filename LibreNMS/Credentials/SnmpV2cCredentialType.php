<?php

/**
 * SnmpV2cCredentialType.php
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

class SnmpV2cCredentialType extends CredentialType
{
    public function name(): string
    {
        return 'SNMP v2c';
    }

    public function validate(array $data): bool
    {
        return isset($data['community']) && is_string($data['community']);
    }

    public function schema(): array
    {
        return [
            'community' => [
                'type' => 'string',
                'label' => 'Community',
                'required' => true,
                'secret' => true,
            ],
        ];
    }

    public function renderUi(): string
    {
        return 'snmp-v2c-ui'; // Placeholder for frontend component
    }

    public function parse(array $data): array
    {
        return [
            'community' => (string) ($data['community'] ?? ''),
        ];
    }
}
