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
 * @author f0o <f0o@devilcode.org>
 * @copyright 2014 f0o, LibreNMS
 * @license GPL
 * @package LibreNMS
 * @subpackage Alerts
 */
namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;

class Slack extends Transport
{
    public function deliverAlert($alert_data)
    {
        if (empty($this->config)) {
            return $this->deliverAlertOld($alert_data);
        }

        return $this->contactSlack(
            $alert_data,
            $this->config['slack-url'],
            $this->config['slack-username'],
            $this->config['slack-icon'],
            $this->config['slack-emoji'],
            $this->config['slack-author'],
            $this->config['slack-channel']
        );
    }

    public function deliverAlertOld($obj)
    {
        foreach ($this->getLegacyConfig() as $slack_config) {
            $slack_opts = [];
            foreach (explode(PHP_EOL, $slack_config['options']) as $option) {
                list($k,$v) = explode('=', $option);
                $slack_opts[$k] = $v;
            }

            $this->contactSlack(
                $obj,
                $slack_config['url'],
                $slack_opts['username'],
                $slack_opts['icon_url'],
                $slack_opts['icon_emoji'],
                $slack_opts['author_name'],
                $slack_opts['channel']
            );
        }
        return true;
    }

    public static function contactSlack($obj, $url, $username, $icon_url, $icon_emoji, $author, $channel)
    {
        $curl          = curl_init();
        $slack_msg     = strip_tags($obj['msg']);
        $color         = ($obj['state'] == 0 ? '#00FF00' : '#FF0000');
        $data          = [
            'attachments' => [
                0 => [
                    'fallback' => $slack_msg,
                    'color' => $color,
                    'title' => $obj['title'],
                    'text' => $slack_msg,
                    'mrkdwn_in' => ['text', 'fallback'],
                    'author_name' => $author,
                ],
            ],
            'channel' => $channel,
            'username' => $username,
            'icon_url' => $icon_url,
            'icon_emoji' => $icon_emoji,
        ];
        $alert_message = json_encode($data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
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
                    'name' => 'slack-url',
                    'descr' => 'Slack Webhook URL',
                    'type' => 'url',
                ],
                [
                    'title' => 'Username (Optional)',
                    'name' => 'slack-username',
                    'descr' => 'Override the default username.',
                    'type' => 'text'
                ],
                [
                    'title' => 'Icon URL (Optional)',
                    'name' => 'slack-icon',
                    'descr' => 'An icon image URL string to use in place of the default icon.',
                    'type' => 'url'
                ],
                [
                    'title' => 'Icon Emoji (Optional)',
                    'name' => 'slack-emoji',
                    'descr' => 'An emoji code string to use in place of the default icon.',
                    'type' => 'text',
                    'pattern' => ':[a-z0-9_]+:'
                ],
                [
                    'title' => 'Author name (Optional)',
                    'name' => 'slack-author',
                    'descr' => 'Override the display name for posts.',
                    'type' => 'text'
                ],
                [
                    'title' => 'Channel (Optional)',
                    'name' => 'slack-channel',
                    'descr' => 'Override the default channel. This should be an ID, such as C8UJ12P4P.',
                    'type' => 'text'
                ]
            ],
            'validation' => [
                'slack-url' => 'required|url',
                'slack-author' => 'string',
                'slack-icon' => 'url',
                'slack-emoji' => 'regex:/:[a-z0-9_]+:/',
                'slack-channel' => 'string',
                'slack-username' => 'string',
            ]
        ];
    }
}
