<?php
/**
* transport-telegram.inc.php
*
* LibreNMS Telegram alerting transport
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
* @copyright  2017 Neil Lathwood
* @author     Neil Lathwood <neil@lathwood.co.uk>
*/
namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;

class Telegram extends Transport
{
    public function deliverAlert($alert_data)
    {
        if ($this->hasLegacyConfig()) {
            return $this->deliverAlertOld($alert_data);
        }

        return $this->contactTelegram($alert_data, $this->config['telegram-chat-id'], $this->config['telegram-token']);
    }

    public function deliverAlertOld($obj)
    {
        foreach ($this->getLegacyConfig() as $data) {
            $this->contactTelegram($obj, $data['chat_id'], $data['token']);
        }
        return true;
    }

    public static function contactTelegram($obj, $chat_id, $token)
    {
        $curl = curl_init();
        set_curl_proxy($curl);
        $text = urlencode($obj['msg']);
        curl_setopt($curl, CURLOPT_URL, ("https://api.telegram.org/bot$chat_id/sendMessage?chat_id=$token&parse_mode=html&text=$text"));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code != 200) {
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
                    'title' => 'Chat ID',
                    'name' => 'telegram-chat-id',
                    'descr' => 'Telegram Chat ID',
                    'type' => 'text',
                ],
                [
                    'title' => 'Token',
                    'name' => 'telegram-token',
                    'descr' => 'Telegram Token',
                    'type' => 'text',
                ]
            ],
            'validation' => [
                'telegram-chat-id' => 'required|string',
                'telegram-token' => 'required|string',
            ]
        ];
    }
}
