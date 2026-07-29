<?php

/**
 * AlertsController.php
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
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Widgets;

use App\Models\AlertRule;
use App\Models\DeviceGroup;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertsController extends WidgetController
{
    protected string $name = 'alerts';
    protected $defaults = [
        'title' => null,
        'device' => null,
        'severity' => [],
        'state' => null,
        'device_group' => null,
        'rule_id' => null,
        'proc' => 0,
        'location' => 1,
        'sort' => 1,
        'hidenavigation' => 0,
        'uncollapse_key_count' => 1,
        'filter' => [],
    ];

    public function getSettings($settingsView = false): array
    {
        $settings = parent::getSettings($settingsView);

        $filter = $settings['filter'] ?? [];

        $legacySeverityMap = [
            '1' => 'ok',
            '2' => 'warning',
            '3' => 'critical',
            '4' => 'ok',
            '5' => 'warning',
            '6' => 'critical',
        ];

        if (! isset($filter['state']) && isset($settings['state']) && $settings['state'] !== '' && $settings['state'] !== null) {
            $filter['state'] = ['eq' => (int) $settings['state']];
        }
        if (! isset($filter['rule.severity']) && ! empty($settings['severity'])) {
            $severities = array_map(fn ($s) => $legacySeverityMap[$s] ?? (string) $s, (array) $settings['severity']);
            $filter['rule.severity'] = ['in' => array_values(array_unique($severities))];
        } elseif (isset($filter['rule.severity']['in'])) {
            $severities = array_map(fn ($s) => $legacySeverityMap[$s] ?? (string) $s, (array) $filter['rule.severity']['in']);
            $filter['rule.severity']['in'] = array_values(array_unique($severities));
        }
        if (! isset($filter['device.groups.id']) && isset($settings['device_group'])) {
            $groupId = $settings['device_group'] instanceof DeviceGroup ? $settings['device_group']->id : $settings['device_group'];
            if ($groupId) {
                $filter['device.groups.id'] = ['eq' => (int) $groupId];
            }
        }
        if (! isset($filter['rule_id']) && isset($settings['rule_id']) && $settings['rule_id'] !== '' && $settings['rule_id'] !== null) {
            $filter['rule_id'] = ['eq' => (int) $settings['rule_id']];
        }
        if (! isset($filter['device_id']) && isset($settings['device']) && $settings['device'] !== '' && $settings['device'] !== null) {
            $filter['device_id'] = ['eq' => (int) $settings['device']];
        }

        $settings['filter'] = $filter;
        $this->settings = $settings;

        return $settings;
    }

    public function getSettingsView(Request $request): View
    {
        $data = $this->getSettings(true);

        $ruleId = $data['filter']['rule_id']['eq'] ?? null;
        if ($ruleId) {
            $data['rule'] = AlertRule::find($ruleId);
        }

        $devGroupId = $data['filter']['device.groups.id']['eq'] ?? null;
        if ($devGroupId && ! ($data['device_group'] instanceof DeviceGroup)) {
            $data['device_group'] = DeviceGroup::find($devGroupId);
        }

        $data['severities'] = [
            'critical' => 'critical',
            'warning' => 'warning',
            'ok' => 'ok',
        ];
        $data['states'] = [
            'recovered' => 0,
            'alerted' => 1,
            'acknowledged' => 2,
            'worse' => 3,
            'better' => 4,
            'changed' => 5,
        ];

        return view('widgets.settings.alerts', $data);
    }
}
