<?php
/**
 * SnmpCredentialData.php
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

namespace App\Data\Credentials;

class SnmpCredentialData extends CredentialData
{
    public function __construct(
        public string $snmp_version = 'v2c',
        public ?string $snmp_community = null,
        public ?string $snmp_v3_auth_name = null,
        public ?string $snmp_v3_auth_pass = null,
        public string $snmp_v3_auth_level = 'noAuthNoPriv',
        public string $snmp_v3_auth_algo = 'SHA',
        public ?string $snmp_v3_crypto_pass = null,
        public string $snmp_v3_crypto_algo = 'AES',
        public ?string $snmp_v3_context = null,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new static(
            snmp_version: $data['snmp_version'] ?? 'v2c',
            snmp_community: $data['snmp_community'] ?? null,
            snmp_v3_auth_name: $data['snmp_v3_auth_name'] ?? null,
            snmp_v3_auth_pass: $data['snmp_v3_auth_pass'] ?? null,
            snmp_v3_auth_level: $data['snmp_v3_auth_level'] ?? 'noAuthNoPriv',
            snmp_v3_auth_algo: $data['snmp_v3_auth_algo'] ?? 'SHA',
            snmp_v3_crypto_pass: $data['snmp_v3_crypto_pass'] ?? null,
            snmp_v3_crypto_algo: $data['snmp_v3_crypto_algo'] ?? 'AES',
            snmp_v3_context: $data['snmp_v3_context'] ?? null,
        );
    }

    public static function rules(): array
    {
        return [
            'snmp_version' => 'required|in:v1,v2c,v3',
            'snmp_community' => 'required_if:snmp_version,v1,v2c|string|nullable',
            'snmp_v3_auth_name' => 'required_if:snmp_version,v3|string|nullable',
            'snmp_v3_auth_pass' => 'required_if:snmp_v3_auth_level,authNoPriv,authPriv|string|nullable',
            'snmp_v3_auth_level' => 'required_if:snmp_version,v3|in:noAuthNoPriv,authNoPriv,authPriv',
            'snmp_v3_auth_algo' => 'required_if:snmp_v3_auth_level,authNoPriv,authPriv|in:MD5,SHA,SHA-224,SHA-256,SHA-384,SHA-512',
            'snmp_v3_crypto_pass' => 'required_if:snmp_v3_auth_level,authPriv|string|nullable',
            'snmp_v3_crypto_algo' => 'required_if:snmp_v3_auth_level,authPriv|in:DES,AES,AES-192,AES-256,AES-192-C,AES-256-C',
        ];
    }

    public static function getUiSchema(): array
    {
        return [
            'snmp_version' => [
                'type' => 'select',
                'label' => 'SNMP Version',
                'options' => [
                    'v1' => 'v1',
                    'v2c' => 'v2c',
                    'v3' => 'v3',
                ],
            ],
            'snmp_community' => [
                'type' => 'password',
                'label' => 'Community',
                'visible_if' => [
                    'snmp_version' => ['$in' => ['v1', 'v2c']],
                ],
            ],
            'snmp_v3_auth_name' => [
                'type' => 'text',
                'label' => 'Auth Name',
                'visible_if' => [
                    'snmp_version' => 'v3',
                ],
            ],
            'snmp_v3_auth_level' => [
                'type' => 'select',
                'label' => 'Auth Level',
                'options' => [
                    'noAuthNoPriv' => 'No Authentication, No Privacy',
                    'authNoPriv' => 'Authentication, No Privacy',
                    'authPriv' => 'Authentication, Privacy',
                ],
                'visible_if' => [
                    'snmp_version' => 'v3',
                ],
            ],
            'snmp_v3_auth_pass' => [
                'type' => 'password',
                'label' => 'Auth Password',
                'visible_if' => [
                    'snmp_version' => 'v3',
                    'snmp_v3_auth_level' => ['$in' => ['authNoPriv', 'authPriv']],
                ],
            ],
            'snmp_v3_auth_algo' => [
                'type' => 'select',
                'label' => 'Auth Algorithm',
                'options' => [
                    'MD5' => 'MD5',
                    'SHA' => 'SHA',
                    'SHA-224' => 'SHA-224',
                    'SHA-256' => 'SHA-256',
                    'SHA-384' => 'SHA-384',
                    'SHA-512' => 'SHA-512',
                ],
                'visible_if' => [
                    'snmp_version' => 'v3',
                    'snmp_v3_auth_level' => ['$in' => ['authNoPriv', 'authPriv']],
                ],
            ],
            'snmp_v3_crypto_pass' => [
                'type' => 'password',
                'label' => 'Crypto Password',
                'visible_if' => [
                    'snmp_version' => 'v3',
                    'snmp_v3_auth_level' => 'authPriv',
                ],
            ],
            'snmp_v3_crypto_algo' => [
                'type' => 'select',
                'label' => 'Crypto Algorithm',
                'options' => [
                    'DES' => 'DES',
                    'AES' => 'AES',
                    'AES-192' => 'AES-192',
                    'AES-256' => 'AES-256',
                    'AES-192-C' => 'AES-192-C',
                    'AES-256-C' => 'AES-256-C',
                ],
                'visible_if' => [
                    'snmp_version' => 'v3',
                    'snmp_v3_auth_level' => 'authPriv',
                ],
            ],
            'snmp_v3_context' => [
                'type' => 'text',
                'label' => 'Context',
                'visible_if' => [
                    'snmp_version' => 'v3',
                ],
            ],
        ];
    }
}
