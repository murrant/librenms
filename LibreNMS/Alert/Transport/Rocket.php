<?php
/* Copyright (C) 2014 Daniel Preussker <f0o@devilcode.org>
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
 * API Transport
 * @author ToeiRei <vbauer@stargazer.at>
 * @copyright 2017 ToeiRei, LibreNMS work based on the work of f0o. It's his work.
 * @license GPL
 * @package LibreNMS
 * @subpackage Alerts
 */
namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;

class Rocket extends Transport
{
    public function deliverAlert($alert_data)
    {
        if ($this->hasLegacyConfig()) {
            return $this->deliverAlertOld($alert_data);
        }

        return $this->contactRocket(
            $alert_data,
            $this->config['rocket-url'],
            $this->config['rocket-username'],
            $this->config['rocket-icon'],
            $this->config['rocket-emoji'],
            $this->config['rocket-channel']
        );
    }

    public function deliverAlertOld($obj)
    {
        foreach ($this->getLegacyConfig() as $rocket_config) {
            $rocket_opts = [];
            foreach (explode(PHP_EOL, $rocket_config['options']) as $option) {
                list($k,$v) = explode('=', $option);
                $rocket_opts[$k] = $v;
            }

            $this->contactRocket(
                $obj,
                $rocket_config['url'],
                $rocket_opts['username'],
                $rocket_opts['icon_url'],
                $rocket_opts['icon_emoji'],
                $rocket_opts['channel']
            );
        }
        return true;
    }

    public static function contactRocket($obj, $url, $username, $icon_url, $icon_emoji, $channel)
    {
        $curl          = curl_init();
        $rocket_msg    = strip_tags($obj['msg']);
        $color         = ($obj['state'] == 0 ? '#00FF00' : '#FF0000');
        $data          = array(
            'attachments' => array(
                0 => array(
                    'fallback' => $rocket_msg,
                    'color' => $color,
                    'title' => $obj['title'],
                    'text' => $rocket_msg,
                )
            ),
            'channel' => $channel,
            'username' => $username,
            'icon_url' => $icon_url,
            'icon_emoji' => $icon_emoji,
        );
        $alert_message = json_encode($data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        set_curl_proxy($curl);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $alert_message);

        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            var_dump("API '$url' returned Error"); //FIXME: proper debuging
            var_dump("Params: " . $alert_message); //FIXME: proper debuging
            var_dump("Return: " . $ret); //FIXME: proper debuging
            return 'HTTP Status code ' . $code;
        }
        return true;
    }

    public static function configTemplate()
    {
        return [
            'config' => [
                [
                    'title' => 'Webhook URL',
                    'name' => 'rocket-url',
                    'descr' => 'Rocket.chat Webhook URL',
                    'type' => 'url',
                ],
                [
                    'title' => 'Username (Optional)',
                    'name' => 'rocket-username',
                    'descr' => 'Override the default username',
                    'type' => 'text'
                ],
                [
                    'title' => 'Icon URL (Optional)',
                    'name' => 'rocket-icon',
                    'descr' => 'An icon image URL string to use in place of the default icon',
                    'type' => 'url'
                ],
                [
                    'title' => 'Icon Emoji (Optional)',
                    'name' => 'rocket-emoji',
                    'descr' => 'An emoji code string to use in place of the default icon.',
                    'type' => 'text',
                    'pattern' => ':[a-z0-9_]+:'
                ],
                [
                    'title' => 'Channel (Optional)',
                    'name' => 'rocket-channel',
                    'descr' => 'Override the default channel.',
                    'type' => 'text'
                ]
            ],
            'validation' => [
                'rocket-url' => 'required|url',
                'rocket-username' => 'string',
                'rocket-icon' => 'url',
                'rocket-emoji' => 'regex:/:[a-z0-9_]+:/',
                'rocket-channel' => 'string',
            ]
        ];
    }
}
