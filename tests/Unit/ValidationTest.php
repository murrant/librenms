<?php
/*
 * ValidationTest.php
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
 * @copyright  2023 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Tests\Unit;

use LibreNMS\Config;
use LibreNMS\ValidationResult;
use LibreNMS\Validations\Configuration\CheckDeprecatedSettings;

class ValidationTest extends \LibreNMS\Tests\TestCase
{
    public function testConfigurationValidation()
    {
        $config_validate = new CheckDeprecatedSettings();

        // check validate is clean
        $this->assertSame(ValidationResult::SUCCESS, $config_validate->validate()->getStatus(), 'System fails validation, tests will fail, run ./validate.php and correct issues');


        foreach(CheckDeprecatedSettings::deprecated as [$old]) {
            Config::set($old, true); // set all deprecated settings just to true for now as we aren't checking fixes
        }

        Config::set('alerts.email.enable', true);
        $result = $config_validate->validate();
        $this->assertSame(ValidationResult::FAILURE, $result->getStatus());
        $this->assertCount(count(CheckDeprecatedSettings::deprecated), $result->getList());
        foreach ($result->getFix() as $fix) {
            $this->assertStringContainsString('config.php', $fix); // all injected settings should assume they are set via config.php
        }

    }
}
