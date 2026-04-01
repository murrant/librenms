<?php
/**
 * IpmiCredentialData.php
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

class IpmiCredentialData extends CredentialData
{
    public function __construct(
        public ?string $username = null,
        public ?string $password = null,
        public ?string $auth_level = 'OPERATOR',
        public ?string $auth_type = 'NONE',
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new static(
            username: $data['username'] ?? null,
            password: $data['password'] ?? null,
            auth_level: $data['auth_level'] ?? 'OPERATOR',
            auth_type: $data['auth_type'] ?? 'NONE',
        );
    }

    public static function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string',
            'auth_level' => 'required|in:CALLBACK,USER,OPERATOR,ADMIN,OEM',
            'auth_type' => 'required|in:NONE,MD2,MD5,PASSWORD,OEM',
        ];
    }
}
