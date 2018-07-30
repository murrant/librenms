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

class Elasticsearch extends Transport
{
    public function deliverAlert($alert_data)
    {
        if ($this->hasLegacyConfig()) {
            return $this->deliverAlertOld($alert_data);
        }

        return $this->contactElasticsearch(
            $alert_data,
            $this->config['es-host'],
            $this->config['es-port'],
            $this->config['es-pattern'],
            $this->config['es-proxy']
        );
    }

    public function deliverAlertOld($obj)
    {
        $legacy_config = $this->getLegacyConfig();
        $host = empty($legacy_config['es_host']) ? '127.0.0.1' : $legacy_config['es_host'];
        $port = isset($legacy_config['es_port']) ? $legacy_config['es_port'] : null;
        $index = isset($legacy_config['es_index']) ? $legacy_config['es_index'] : null;
        $proxy = !empty($legacy_config['es_proxy']);

        return $this->contactElasticsearch($obj, $host, $port, $index, $proxy);
    }

    /**
     * @param string $host
     * @param int $port
     * @param string $index
     * @return string
     * @throws \Exception
     */
    private function buildUri($host, $port, $index)
    {
        $es_host  = $host;
        $es_port  = ctype_digit($port) ? $port : 9200;
        $es_index = strftime(empty($index) ? "librenms-%Y.%m.%d" : $index);
        $type     = 'alert';

        if (preg_match("/[a-zA-Z]/", $es_host)) {
            $es_host = gethostbyname($es_host);
            if ($host === $es_host) {
                throw new \Exception("Alphanumeric hostname found but does not resolve to an IP.");
            }
        } elseif (!IP::isValid($host)) {
            throw new \Exception("Elasticsearch host is not a valid IP: " . $host);
        }

        return $es_host . ':' . $es_port . '/' . $es_index . '/' . $type;
    }

    public function contactElasticsearch($obj, $host, $port, $index, $proxy)
    {
        $severity = $obj['severity'];

        try {
            $uri = $this->buildUri($host, $port, $index);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        switch ($obj['state']) {
            case 0:
                $state = "ok";
                break;
            case 1:
                $state = $severity;
                break;
            case 2:
                $state = "acknowledged";
                break;
            case 3:
                $state = "worse";
                break;
            case 4:
                $state = "better";
                break;
        }

        $data = array(
            '@timestamp' => strftime("%Y-%m-%dT%T"),
            "host" => gethostname(),
            "location" => $obj['location'],
            "title" => $obj['name'],
            "message" => $obj['string'],
            "device_id" => $obj['device_id'],
            "device_name" => $obj['hostname'],
            "device_hardware" => $obj['hardware'],
            "device_version" => $obj['version'],
            "state" => $state,
            "severity" => $severity,
            "first_occurrence" => $obj['timestamp'],
            "entity_type" => "device",
            "entity_tab" => "overview",
            "entity_id" => $obj['device_id'],
            "entity_name" => $obj['hostname'],
            "entity_descr" => $obj['sysDescr'],
        );

        if (!empty($obj['faults'])) {
            foreach ($obj['faults'] as $k => $v) {
                $curl            = curl_init();
                $data['message'] = $v['string'];
                switch (true) {
                    case (array_key_exists('port_id', $v)):
                        $data['entity_type']  = 'port';
                        $data['entity_tab']   = 'port';
                        $data['entity_id']    = $v['port_id'];
                        $data['entity_name']  = $v['ifName'];
                        $data['entity_descr'] = $v['ifAlias'];
                        break;
                    case (array_key_exists('sensor_id', $v)):
                        $data['entity_type']  = $v['sensor_class'];
                        $data['entity_tab']   = 'health';
                        $data['entity_id']    = $v['sensor_id'];
                        $data['entity_name']  = $v['sensor_descr'];
                        $data['entity_descr'] = $v['sensor_type'];
                        break;
                    case (array_key_exists('mempool_id', $v)):
                        $data['entity_type']  = 'mempool';
                        $data['entity_tab']   = 'health';
                        $data['entity_id']    = $v['mempool_id'];
                        $data['entity_name']  = $v['mempool_index'];
                        $data['entity_descr'] = $v['mempool_descr'];
                        break;
                    case (array_key_exists('storage_id', $v)):
                        $data['entity_type']  = 'storage';
                        $data['entity_tab']   = 'health';
                        $data['entity_id']    = $v['storage_id'];
                        $data['entity_name']  = $v['storage_index'];
                        $data['entity_descr'] = $v['storage_descr'];
                        break;
                    case (array_key_exists('processor_id', $v)):
                        $data['entity_type']  = 'processor';
                        $data['entity_tab']   = 'health';
                        $data['entity_id']    = $v['processor_id'];
                        $data['entity_name']  = $v['processor_type'];
                        $data['entity_descr'] = $v['processor_descr'];
                        break;
                    case (array_key_exists('bgpPeer_id', $v)):
                        $data['entity_type']  = 'bgp';
                        $data['entity_tab']   = 'routing';
                        $data['entity_id']    = $v['bgpPeer_id'];
                        $data['entity_name']  = 'local: ' . $v['bgpPeerLocalAddr'] . ' - AS' . $obj['bgpLocalAs'];
                        $data['entity_descr'] = 'remote: ' . $v['bgpPeerIdentifier'] . ' - AS' . $v['bgpPeerRemoteAs'];
                        break;
                    case (array_key_exists('tunnel_id', $v)):
                        $data['entity_type']  = 'ipsec_tunnel';
                        $data['entity_tab']   = 'routing';
                        $data['entity_id']    = $v['tunnel_id'];
                        $data['entity_name']  = $v['tunnel_name'];
                        $data['entity_descr'] = 'local: ' . $v['local_addr'] . ':' . $v['local_port'] . ', remote: ' . $v['peer_addr'] . ':' . $v['peer_port'];
                        break;
                    default:
                        $data['entity_type'] = 'generic';
                        break;
                }
                $alert_message = json_encode($data);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                if ($proxy === true) {
                    set_curl_proxy($curl);
                }
                curl_setopt($curl, CURLOPT_URL, $uri);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $alert_message);

                $ret  = curl_exec($curl);
                $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if ($code != 200 && $code != 201) {
                    return $uri . ' returned HTTP Status code ' . $code . ' for ' . $alert_message;
                }
            }
        } else {
            $curl          = curl_init();
            $alert_message = json_encode($data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            if ($proxy === true) {
                set_curl_proxy($curl);
            }
            curl_setopt($curl, CURLOPT_URL, $uri);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $alert_message);

            $ret  = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($code != 200 && $code != 201) {
                return $uri . ' returned HTTP Status code ' . $code . ' for ' . $alert_message;
            }
        }
        return true;
    }

    public static function configTemplate()
    {
        return [
            'config' => [
                [
                    'title' => 'Host',
                    'name' => 'es-host',
                    'descr' => 'Elasticsearch Host',
                    'type' => 'text',
                ],
                [
                    'title' => 'Port',
                    'name' => 'es-port',
                    'descr' => 'Elasticsearch Port',
                    'type' => 'number',
                    'default' => '9200'
                ],
                [
                    'title' => 'Index Pattern',
                    'name' => 'es-pattern',
                    'descr' => 'Elasticsearch Index Pattern',
                    'type' => 'text',
                    'default' => 'librenms-%Y.%m.%d'
                ],
                [
                    'title' => 'Use proxy if configured?',
                    'name' => 'es-proxy',
                    'descr' => 'Elasticsearch Proxy',
                    'type' => 'checkbox',
                    'default' => false
                ]
            ],
            'validation' => [
                'es-host' => 'required|string',
                'es-port' => 'required|int',
                'es-pattern' => 'required|string'
            ]
        ];
    }
}
