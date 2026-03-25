<?php
/**
 * Credential.php
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

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Crypt;
use LibreNMS\Credentials\CredentialType;

class Credential extends BaseModel
{
    protected $fillable = ['name', 'type', 'version', 'data', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected function data(): Attribute
    {
        return Attribute::make(
            get: function (string $value) {
                try {
                    return json_decode(Crypt::decryptString($value), true);
                } catch (\Exception $e) {
                    return null;
                }
            },
            set: fn (array $value) => Crypt::encryptString(json_encode($value)),
        )->shouldCache();
    }

    public function getCredentialType(): ?CredentialType
    {
        if (class_exists($this->type)) {
            return new $this->type();
        }

        return null;
    }


    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'credential_device', 'credential_id', 'device_id')
            ->withPivot('order');
    }
}
