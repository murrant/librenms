<?php
/* LibreNMS
 *
 * Copyright (C) 2017 Paul Blasquez <pblasquez@gmail.com>
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
namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;
use LibreNMS\Util\IP;

class Syslog extends Transport
{
    public function deliverAlert($alert_data)
    {
        if ($this->hasLegacyConfig()) {
            return $this->deliverAlertOld($alert_data);
        }

        return $this->contactSyslog(
            $alert_data,
            $this->config['syslog-host'],
            $this->config['syslog-port'],
            $this->config['syslog-facility']
        );
    }


    public function deliverAlertOld($obj)
    {
        $legacy_config = $this->getLegacyConfig();

        return $this->contactSyslog(
            $obj,
            $legacy_config['syslog_host'],
            $legacy_config['syslog_port'],
            $legacy_config['syslog_facility']
        );
    }

    public function contactSyslog($obj, $host, $port, $facility)
    {
        $syslog_host = '127.0.0.1';
        $syslog_port = 514;
        $state       = "Unknown";

        $severity    = 6; // Default severity is 6 (Informational)
        $sev_txt     = "OK";
        $device      = device_by_id_cache($obj['device_id']); // for event logging

        if (preg_match("/^\d+$/", $facility)) {
            $facility = (int)$facility * 8;
        } else {
            log_event("Syslog facility is not an integer: " . $facility, $device, 'alert', 5);
            $facility = 24; // Default facility is 3 * 8 (daemon)
        }

        if (!empty($host)) {
            if (preg_match("/[a-zA-Z]/", $host)) {
                $syslog_host = gethostbyname($host);
                if ($syslog_host === $host) {
                    log_event("Alphanumeric hostname found but does not resolve to an IP.", $device, 'alert', 5);
                    return false;
                }
            } elseif (IP::isValid($host)) {
                $syslog_host = $host;
            } else {
                log_event("Syslog host is not a valid IP: $host", $device, 'alert', 5);
                return false;
            }
        } else {
            log_event("Syslog host is empty.", $device, 'alert');
        }

        if (preg_match("/^\d+$/", $port)) {
            $syslog_port = $port;
        } else {
            log_event("Syslog port is not an integer.", $device, 'alert', 5);
        }

        switch ($obj['severity']) {
            case "critical":
                $severity = 2;
                $sev_txt  = "Critical";
                break;
            case "warning":
                $severity = 4;
                $sev_txt  = "Warning";
                break;
        }

        switch ($obj['state']) {
            case 0:
                $state    = "OK";
                $severity = 6;
                break;
            case 1:
                $state = $sev_txt;
                break;
            case 2:
                $state    = "Acknowledged";
                $severity = 6;
                break;
        }

        $priority = $facility + $severity;

        $syslog_prefix = '<'
            . $priority
            . '> '
            . date('M d H:i:s ')
            . gethostname()
            . ' librenms'
            . '['
            . $obj['device_id']
            . ']: '
            . $obj['hostname']
            . ': ['
            . $state
            . '] '
            . $obj['name'];

        if (($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) === false) {
            log_event("socket_create() failed: reason: " . socket_strerror(socket_last_error()), $device, 'poller', 5);
            return false;
        } else {
            if (!empty($obj['faults'])) {
                foreach ($obj['faults'] as $k => $v) {
                    $syslog_msg = $syslog_prefix . ' - ' . $v['string'];
                    socket_sendto($socket, $syslog_msg, strlen($syslog_msg), 0, $syslog_host, $syslog_port);
                }
            } else {
                $syslog_msg = $syslog_prefix;
                socket_sendto($socket, $syslog_msg, strlen($syslog_msg), 0, $syslog_host, $syslog_port);
            }
            socket_close($socket);
        }
        return true;
    }

    public static function configTemplate()
    {
        return [
            'config' => [
                [
                    'title' => 'Host',
                    'name' => 'syslog-host',
                    'descr' => 'Syslog Host',
                    'type' => 'text'
                ],
                [
                    'title' => 'Port',
                    'name' => 'syslog-port',
                    'descr' => 'Syslog Port',
                    'type' => 'number',
                    'default' => '514'
                ],
                [
                    'title' => 'Facility',
                    'name' => 'syslog-facility',
                    'descr' => 'Syslog Facility (0-23)',
                    'type' => 'number',
                    'default' => '3'
                ]
            ],
            'validation' => [
                'syslog-host' => 'required|string',
                'syslog-port' => 'required|numeric',
                'syslog-facility' => 'required|string'
            ]
        ];
    }
}
