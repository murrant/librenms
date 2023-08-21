<?php
/**
 * CheckAppKeyDecryption.php
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
 * @copyright  2023 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Validations\Configuration;

use Illuminate\Contracts\Encryption\DecryptException;
use LibreNMS\Config;
use LibreNMS\Interfaces\Validation;
use LibreNMS\ValidationResult;

class CheckAppKeyDecryption implements Validation
{

    public function validate(): ValidationResult
    {
        if (Config::has('validation.encryption.test')) {
            try {
                if (\Crypt::decryptString(Config::get('validation.encryption.test')) !== 'librenms') {
                    return $this->failKeyChanged();
                }
            } catch (DecryptException $e) {
                return $this->failKeyChanged();
            }
        } else {
            Config::persist('validation.encryption.test', \Crypt::encryptString('librenms'));
        }

        return ValidationResult::ok(trans('validation.validations.configuration.CheckAppKeyDecryption.ok'));
    }

    private function failKeyChanged(): ValidationResult
    {
        return ValidationResult::fail(
            trans('validation.validations.configuration.CheckAppKeyDecryption.fail'),
            trans('validation.validations.configuration.CheckAppKeyDecryption.fix')
        );
    }

    public function enabled(): bool
    {
        return true;
    }
}
