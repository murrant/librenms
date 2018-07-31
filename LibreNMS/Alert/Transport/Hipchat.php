<?php
/* Copyright (C) 2014 Daniel Preussker <f0o@devilcode.org>, Tyler Christiansen <code@tylerc.me>
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

/*
 * API Transport
 * @author Tyler Christiansen <code@tylerc.me>
 * @copyright 2014 Daniel Preussker, Tyler Christiansen, LibreNMS
 * @license GPL
 * @package LibreNMS
 * @subpackage Alerts
 */
namespace LibreNMS\Alert\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LibreNMS\Alert\Transport;

class Hipchat extends Transport
{
    public function deliverAlert($alert_data)
    {
        if ($this->hasLegacyConfig()) {
            return $this->deliverAlertOld($alert_data);
        }

        return $this->contactHipchat(
            $alert_data,
            $this->config['hipchat-url'],
            $this->config['hipchat-room-id'],
            $this->config['hipchat-token'],
            $this->config['hipchat-from-name'],
            $this->config['hipchat-notify'],
            $this->config['hipchat-color'],
            $this->config['hipchat-format'],
            $this->config['hipchat-options']
        );
    }

    public function deliverAlertOld($alert_data)
    {
        // loop through each room (legacy config splits options and sets them all)
        foreach ($this->getLegacyConfig() as $hipchat_config) {
            $this->contactHipchat(
                $alert_data,
                $hipchat_config['url'],
                $hipchat_config['room_id'],
                $hipchat_config['auth_token'],
                $hipchat_config['from'],
                $hipchat_config['notify'],
                $hipchat_config['color'],
                $hipchat_config['message_format'],
                $hipchat_config['options']
            );
        }
        return true;
    }

    public function contactHipchat($obj, $url, $room_id, $auth_token, $from, $notify, $color, $format, $options = '')
    {
        if (empty($obj["msg"])) {
            return "Empty Message";
        }

        // remove /v2/room from the url, we just need the base url
        $base_url = rtrim(str_replace('/v2/room', '', $url), '/');

        $client = new Client([
            'base_uri' => $base_url,
            'timeout' => 2.0,
            'headers' => ['Authorization' => 'Bearer ' . $auth_token]
        ]);

        // Sane default of making the message color green if the message indicates
        // that the alert recovered.   If it rebooted, make it yellow.
        if (stripos($obj["msg"], "recovered") !== false) {
            $color = "green";
        } elseif (stripos($obj["msg"], "rebooted") !== false) {
            $color = "yellow";
        } elseif (empty($color) || $color == 'u') {
            $color = 'red';
        }

        $data = [
            'message' => $obj["msg"],
            'from'    => $from,
            'color'   => $color,
            'notify'  => $notify,
            'message_format' => $format ?: 'text',
        ];

        // load additional options
        foreach (explode(PHP_EOL, $options) as $option) {
            list($k,$v) = explode('=', $option);
            $data[$k] = $v;
        }

        try {
            $response = $client->request('POST', "/v2/room/$room_id/notification", ['json' => $data]);

            $code = $response->getStatusCode();
            if ($code != 200 && $code != 204) {
                return "Hipchat API return status code $code: " . $response->getReasonPhrase();
            }
        } catch (GuzzleException $e) {
            return $e->getMessage();
        }

        return true;
    }

    public static function configTemplate()
    {
        return [
            'config' => [
                [
                    'title' => 'API URL',
                    'name' => 'hipchat-url',
                    'descr' => 'Hipchat API URL',
                    'type' => 'url',
                ],
                [
                    'title' => 'Room ID',
                    'name' => 'hipchat-room-id',
                    'descr' => 'Hipchat Room ID',
                    'type' => 'number',
                ],
                [
                    'title' => 'Auth Token',
                    'name' => 'hipchat-token',
                    'descr' => 'Hipchat Auth Token, required for api version 2',
                    'type' => 'text',
                ],
                [
                    'title' => 'From Name',
                    'name' => 'hipchat-from-name',
                    'descr' => 'From Name',
                    'type' => 'text',
                    'default' => 'LibreNMS',
                ],
                [
                    'title' => 'Notify',
                    'name' => 'hipchat-notify',
                    'descr' => 'Notify user',
                    'type'  => 'checkbox',
                    'default' => false,
                ],
                [
                    'title' => 'Color (Optional)',
                    'name' => 'hipchat-color',
                    'descr' => 'Hipchat default message color',
                    'type' => 'text',
                ],
                [
                    'title' => 'Format',
                    'name' => 'hipchat-format',
                    'descr' => 'Hipchat message format',
                    'type' => 'select',
                    'options' => [
                        'text' => 'text',
                        'html' => 'html'
                    ]
                ],
                [
                    'title' => 'Additional Options',
                    'name' => 'hipchat-options',
                    'descr' => 'Hipchat Options. key=value on each line',
                    'type' => 'textarea',
                ],
            ],
            'validation' => [
                'hipchat-url' => 'required|url',
                'hipchat-room-id' => 'required|numeric',
                'hipchat-token' => 'required',
            ]
        ];
    }
}
