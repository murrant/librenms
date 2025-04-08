<?php

/**
 * EditController.php
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
 * @copyright  2020 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Device\Tabs;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\PollerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Config;
use LibreNMS\Util\Debug;
use LibreNMS\Util\Url;

class EditController implements \LibreNMS\Interfaces\UI\DeviceTab
{
    public function visible(Device $device): bool
    {
        return false;
    }

    public function slug(): string
    {
        return 'edit';
    }

    public function icon(): string
    {
        return 'fa-gear';
    }

    public function name(): string
    {
        return __('Edit');
    }

    public function data(Device $device, Request $request): array
    {
        Gate::authorize('manage-device', $device);

        if (preg_match('#section=([a-z\-]+)#', $request->path(), $section_matches)) {
            $section = $section_matches[1];
        } else {
            $section = 'device';
        }

        $data = [
            'edit_section' => $section,
            'edit_sections' => $this->getEditTabs($device),
            'section_content' => $this->getLegacyContent($device, $section),
        ];

        if ($section == 'snmp') {
            $data['poller_groups'] = PollerGroup::pluck('group_name', 'id');
        }

        return $data;
    }

    private function getEditTabs(Device $device): array
    {
        $panes = [
            'device' => 'Device Settings',
            'snmp' => 'SNMP',
        ];

        if (! $device->snmp_disable) {
            $panes['ports'] = 'Port Settings';
        }
        if ($device->bgppeers()->exists()) {
            $panes['routing'] = 'Routing';
        }
        if (count(LibrenmsConfig::get("os.{$device->os}.icons", []))) {
            $panes['icon'] = 'Icon';
        }
        if (! $device->snmp_disable) {
            $panes['apps'] = 'Applications';
        }
        $panes['alert-rules'] = 'Alert Rules';
        if (! $device->snmp_disable) {
            $panes['modules'] = 'Modules';
        }
        if (LibrenmsConfig::get('show_services')) {
            $panes['services'] = 'Services';
        }
        $panes['ipmi'] = 'IPMI';
        if ($device->sensors()->exists()) {
            $panes['health'] = 'Health';
        }

        if ($device->wirelessSensors()->exists()) {
            $panes['wireless-sensors'] = 'Wireless Sensors';
        }
        if (! $device->snmp_disable) {
            $panes['storage'] = 'Storage';
            $panes['processors'] = 'Processors';
            $panes['mempools'] = 'Memory';
        }
        $panes['misc'] = 'Misc';
        $panes['component'] = 'Components';
        $panes['customoid'] = 'Custom OID';

        $tabs = [];
        foreach ($panes as $pane => $text) {
            $tabs[$pane] = [
                'text' => $text,
                'link' => Url::deviceUrl($device, ['tab' => 'edit', 'section' => $pane]),
            ];
        }

        return $tabs;
    }

    private function getLegacyContent(Device $device, string $section): string
    {
        $file = base_path("includes/html/pages/device/edit/$section.inc.php");

        if (! file_exists($file)) {
            return '';
        }

        ob_start();
        $device = $device->toArray();
        $device['os_group'] = Config::get("os.{$device['os']}.group");
        Debug::set(false);
        chdir(base_path());
        $init_modules = ['web', 'auth'];
        require base_path('includes/init.php');

        $vars['device'] = $device['device_id'];
        $vars['tab'] = 'edit';
        $vars['section'] = $section;

        include $file;
        $output = ob_get_clean();
        ob_end_clean();

        return $output;
    }
}
