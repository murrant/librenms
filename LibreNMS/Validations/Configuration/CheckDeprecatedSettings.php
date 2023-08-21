<?php
/**
 * CheckDeprecatedSettings.php
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

use LibreNMS\Config;
use LibreNMS\DB\Eloquent;
use LibreNMS\Interfaces\Validation;
use LibreNMS\Interfaces\ValidationFixer;
use LibreNMS\ValidationResult;

class CheckDeprecatedSettings implements Validation, ValidationFixer
{
    public const deprecated = [
        ['alerts.email.enable', null, false],
        ['discovery_modules.cisco-sla', 'discovery_modules.slas', true],
        ['discovery_modules.cisco-vrf', 'discovery_modules.vrf', true],
        ['discovery_modules.toner', 'discovery_modules.printer-supplies', true],
        ['force_hostname_to_sysname', 'device_display_default', false],
        ['force_ip_to_sysname', 'device_display_default', false],
        ['fping_options.millisec', 'fping_options.interval', true],
        ['oxidized.group', 'oxidized.maps.group', true],
        ['poller_modules.cisco-sla', 'poller_modules.slas', true],
        ['poller_modules.toner', 'poller_modules.printer-supplies', true],
        ['rrdgraph_real_95th', 'rrdgraph_real_percentile', true],
    ];

    public function validate(): ValidationResult
    {
        $failures = [];
        $fixes = [];
        $fixable = false;

        foreach (self::deprecated as [$old, $new]) {
            if (! Config::has($old)) {
                // old setting doesn't exist, skip
                continue;
            }

            // if no new setting location nothing to migrate, just remove
            if (empty($new)) {
                $failures[] = trans('validation.validations.configuration.CheckDeprecatedSettings.fail_remove', ['old' => $old]);

                // mark fixable if set in the database
                if ($this->settingExistsInDatabase($old)) {
                    $fixable = true;
                }
                continue;
            }

            // data already migrated, just need to remove the old setting
            if ($this->settingExistsInDatabase($new)) {
                // check if setting is in the database, might be fixable
                if ($this->settingExistsInDatabase($old)) {
                    $fixes[] = "lnms tinker --execute=\"\App\Models\Config::where('config_name', '$old')->delete()\"";
                    $fixable = true;
                } else {
                    $setting_text = '$config[\'' . str_replace('.', "']['", $old) . "'] = " . var_export(Config::get($old), true) . ';';
                    $fixes[] = trans('validation.validations.configuration.CheckDeprecatedSettings.fix_delete', ['setting' => $setting_text]);
                }

                $failures[] = trans('validation.validations.configuration.CheckDeprecatedSettings.fail_remove', ['old' => $old]);
                continue; // next setting
            }

            // new setting doesn't exist, needs to be migrated
            $fixable = true;
            $failures[] = trans('validation.validations.configuration.CheckDeprecatedSettings.fail_migrate', ['old' => $old, 'new' => $new]);
        }

        if ($failures) {
            return ValidationResult::fail(trans('validation.validations.configuration.CheckDeprecatedSettings.fail'))
                ->setList('Settings', $failures)
                ->setFix($fixes)
                ->setFixer(__CLASS__, $fixable);
        }

        return ValidationResult::ok(trans('validation.validations.configuration.CheckDeprecatedSettings.ok'));
    }

    public function enabled(): bool
    {
        return Eloquent::isConnected();
    }

    public function fix(): bool
    {
        $success = true;
        foreach (self::deprecated as [$old, $new, $copy]) {
            // migrate settings if applicable
            $this->migrate($old, $new, $copy);

            // clean up old setting if it is in the database
            \App\Models\Config::where('config_name', $old)->delete();
        }

        // reload configuration to check if successful
        Config::reload();

        // check if deprecated settings still exist
        foreach (self::deprecated as [$old]) {
            if (Config::has($old)) {
                $success = false;
            }
        }

        return $success;
    }

    private function migrate(string $old, ?string $new, bool $copy): void
    {
        switch ($old) {
            case 'force_hostname_to_sysname':
                // fall-through
            case 'force_ip_to_sysname':
                if (! $this->settingExistsInDatabase('device_display_default')) {
                    $display_value = '{{ $hostname }}';
                    if (Config::get('force_hostname_to_sysname')) {
                        $display_value = '{{ $sysName }}';
                    } elseif (Config::get('force_ip_to_sysname')) {
                        $display_value = '{{ $sysName_fallback }}';
                    }

                    Config::persist('device_display_default', $display_value);
                }

                return;
            default:
                // copy if applicable, but don't overwrite
                if ($copy && ! $this->settingExistsInDatabase($new) && Config::get($old) != Config::get($new)) {
                    Config::persist($new, Config::get($old));
                }
        }
    }

    private function settingExistsInDatabase(string $name): bool
    {
        return \App\Models\Config::where('config_name', $name)->exists();
    }
}
