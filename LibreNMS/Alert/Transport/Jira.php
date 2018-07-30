<?php
/* Copyright (C) 2015 Aldemir Akpinar <aldemir.akpinar@gmail.com>
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
 * Jira API Transport
 * @author  Aldemir Akpinar <aldemir.akpinar@gmail.com>
 * @copyright 2017 Aldemir Akpinar, LibreNMS
 * @license GPL
 * @package LibreNMS
 * @subpackage Alerts
 */
namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;

class Jira extends Transport
{
    public function deliverAlert($alert_data)
    {
        if ($this->hasLegacyConfig()) {
            return $this->deliverAlertOld($alert_data);
        }
        return $this->contactJira(
            $alert_data,
            $this->config['jira-username'],
            $this->config['jira-password'],
            $this->config['jira-key'],
            $this->config['jira-type'],
            $this->config['jira-url']
            );
    }

    public function deliverAlertOld($obj)
    {
        $legacy_config = $this->getLegacyConfig();

        return $this->contactJira(
            $obj,
            $legacy_config['username'],
            $legacy_config['password'],
            $legacy_config['prjkey'],
            $legacy_config['issuetype'],
            $legacy_config['url']
        );
    }

    public function contactJira($obj, $username, $password, $prjkey, $issuetype, $base_url)
    {
        // Don't create tickets for resolutions
        if ($obj['severity'] == 'recovery' && $obj['msg'] != 'This is a test alert') {
            return true;
        }

        $device = device_by_id_cache($obj['device_id']); // for event logging


        $details     = "Librenms alert for: " . $obj['hostname'];
        $description = $obj['msg'];
        $url         = "$base_url/rest/api/latest/issue";
        $curl        = curl_init();

        $datastring = json_encode([
            "fields" => [
                "project" => ["key" => $prjkey],
                "summary" => $details,
                "description" => $description,
                "issuetype" => ["name" => $issuetype]
            ]
        ]);

        set_curl_proxy($curl);

        $headers = array('Accept: application/json', 'Content-Type: application/json');

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $datastring);

        $ret  = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code == 200) {
            $jiraout = json_decode($ret, true);
            d_echo("Created jira issue " . $jiraout['key'] . " for " . $device);
            return true;
        } else {
            d_echo("Jira connection error: " . serialize($ret));
            return false;
        }
    }

    public static function configTemplate()
    {
        return [
            'config' => [
                [
                    'title' => 'URL',
                    'name' => 'jira-url',
                    'descr' => 'Jira URL',
                    'type' => 'url'
                ],
                [
                    'title' => 'Project Key',
                    'name' => 'jira-key',
                    'descr' => 'Jira Project Key',
                    'type' => 'text'
                ],
                [
                    'title' => 'Issue Type',
                    'name' => 'jira-type',
                    'descr' => 'Jira Issue Type',
                    'type' => 'text'
                ],
                [
                    'title' => 'Jira Username',
                    'name' => 'jira-username',
                    'descr' => 'Jira Username',
                    'type' => 'text'
                ],
                [
                    'title' => 'Jira Password',
                    'name' => 'jira-password',
                    'descr' => 'Jira Password',
                    'type' => 'password'
                ],
            ],
            'validation' => [
                'jira-key' => 'required|string',
                'jira-url' => 'required|url',
                'jira-type' => 'required|string',
                'jira-username' => 'required|string',
                'jira-password' => 'required|string',
            ]
        ];
    }
}
