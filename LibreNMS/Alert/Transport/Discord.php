<?php
/**
 * Discord.php
 *
 * LibreNMS Discord API Tranport
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
 * @copyright  2018 Ryan Finney
 * @author     https://github.com/theherodied/
 * @contributer f0o, sdef2
 * Thanks to F0o <f0o@devilcode.org> for creating the Slack transport which is the majority of this code.
 * Thanks to sdef2 for figuring out the differences needed to make Discord work.
 */

namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;

class Discord extends Transport
{
    public function deliverAlert($alert_data)
    {
        if ($this->hasLegacyConfig()) {
            return $this->deliverAlertOld($alert_data);
        }

        return $this->contactDiscord(
            $alert_data,
            $this->config['url'],
            $this->config['discord-username'],
            $this->config['discord-avatar'],
            $this->config['discord-tts']
        );
    }

    public function deliverAlertOld($obj)
    {
        foreach ($this->getLegacyConfig() as $discord_config) {
            $discord_opts = [];
            foreach (explode(PHP_EOL, $discord_config['options']) as $option) {
                list($k,$v) = explode('=', $option);
                $discord_opts[$k] = $v;
            }

            $this->contactDiscord(
                $obj,
                $discord_config['url'],
                $discord_opts['username'],
                $discord_opts['avatar_url'],
                $discord_opts['tts']
            );
        }
        return true;
    }

    public function contactDiscord($obj, $url, $username, $avatar, $tts)
    {
        $curl          = curl_init();

        $data = [
            'content' => "". $obj['title'] ."\n" . strip_tags($obj['msg']),
            'username' => $username,
            'avatar_url' => $avatar,
            'tts' => $tts
        ];

        $alert_message = json_encode($data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        set_curl_proxy($curl);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $alert_message);

        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code != 204) {
            var_dump("API '$url' returned Error"); //FIXME: propper debuging
            var_dump("Params: " . $alert_message); //FIXME: propper debuging
            var_dump("Return: " . $ret); //FIXME: propper debuging
            return 'HTTP Status code ' . $code;
        }
        return true;
    }

    public static function configTemplate()
    {
        return [
            'config' => [
                [
                    'title' => 'Discord URL',
                    'name' => 'url',
                    'descr' => 'Discord URL',
                    'type' => 'url',
                ],
                [
                    'title' => 'Username (Optional)',
                    'name' => 'discord-username',
                    'descr' => 'Override the useranme',
                    'type' => 'text',
                ],
                [
                    'title' => 'Avatar Url (Optional)',
                    'name' => 'discord-avatar',
                    'descr' => 'Override the avatar image url',
                    'type' => 'url',
                ],
                [
                    'title' => 'TTS',
                    'name' => 'discord-tts',
                    'descr' => 'Enable Text-to-Speech for all messages',
                    'type'  => 'checkbox',
                    'default' => false,
                ]
            ],
            'validation' => [
                'url' => 'required|url',
                'discord-username' => 'required|url',
                'discord-avatar' => 'required|url',
            ]
        ];
    }
}
