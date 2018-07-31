<?php
/* Copyright (C) 2015 James Campbell <neokjames@gmail.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>. */

/* Copyright (C) 2015 Daniel Preussker <f0o@devilcode.org>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>. */

/**
 * Pushover API Transport
 * @author neokjames <neokjames@gmail.com>
 * @copyright 2015 neokjames, f0o, LibreNMS
 * @license GPL
 * @package LibreNMS
 * @subpackage Alerts
 */
namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;

class Pushover extends Transport
{
    public function deliverAlert($alert_data)
    {
        if ($this->hasLegacyConfig()) {
            return $this->deliverAlertOld($alert_data);
        }

        return $this->contactPushover(
            $alert_data,
            $this->config['appkey'],
            $this->config['userkey'],
            $this->config['pushover-critical'],
            $this->config['pushover-warning'],
            $this->config['pushover-ok'],
            $this->config['options']
        );
    }

    public function deliverAlertOld($alert_data)
    {
        foreach ($this->getLegacyConfig() as $pushover_config) {
            $pushover_opts = [];
            foreach (explode(PHP_EOL, $pushover_config['options']) as $option) {
                list($k,$v) = explode('=', $option);
                $pushover_opts[$k] = $v;
            }

            $response = $this->contactPushover(
                $alert_data,
                $pushover_config['appkey'],
                $pushover_config['userkey'],
                $pushover_opts['sound_critical'],
                $pushover_opts['sound_warning'],
                $pushover_opts['sound_ok'],
                $pushover_opts
            );
            if ($response !== true) {
                return $response;
            }
        }
        return true;
    }

    public function contactPushover($obj, $token, $user, $critical, $warning, $ok, $extra = [])
    {
        $data = [
            'token' => $token,
            'user' => $user,
            'title' => $obj['title'],
            'message' => $obj['msg'],
        ];

        switch ($obj['severity']) {
            case "critical":
                $data['priority'] = 1;
                if (!empty($critical)) {
                    $data['sound'] = $critical;
                }
                break;
            case "warning":
                $data['priority'] = 1;
                if (!empty($warning)) {
                    $data['sound'] = $warning;
                }
                break;
        }
        switch ($obj['state']) {
            case 0:
                $data['priority'] = 0;
                if (!empty($ok)) {
                    $data['sound'] = $ok;
                }
                break;
        }

        if ($extra) {
            $data = array_merge($data, $extra);
        }
        $curl = curl_init();
        set_curl_proxy($curl);
        curl_setopt($curl, CURLOPT_URL, 'https://api.pushover.net/1/messages.json');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            var_dump("Pushover returned error"); //FIXME: proper debugging
            return 'HTTP Status code ' . $code;
        }
        return true;
    }

    public static function configTemplate()
    {
        return [
            'config' => [
                [
                    'title' => 'Api Key',
                    'name'  => 'appkey',
                    'descr' => 'Api Key',
                    'type'  => 'text',
                ],
                [
                    'title' => 'User Key',
                    'name'  => 'userkey',
                    'descr' => 'User Key',
                    'type'  => 'text',
                ],
                [
                    'title' => 'Critical Sound (Optional)',
                    'name' => 'pushover-critical',
                    'descr' => 'Notification sound for critical alerts',
                    'type' => 'text',
                ],
                [
                    'title' => 'Warning Sound (Optional)',
                    'name' => 'pushover-warning',
                    'descr' => 'Notification sound for warning alerts',
                    'type' => 'text',
                ],
                [
                    'title' => 'OK Sound (Optional)',
                    'name' => 'pushover-ok',
                    'descr' => 'Notification sound when alerts are cleared',
                    'type' => 'text',
                ],
                [
                    'title' => 'Pushover Options',
                    'name'  => 'options',
                    'descr' => 'Pushover extra options',
                    'type'  => 'textarea',

                ],
            ],
            'validation' => [
                'appkey' => 'required',
                'userkey' => 'required',
            ]
        ];
    }
}
