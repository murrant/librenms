<?php
/**
 * powerdns-recursor.inc.php
 *
 * PowerDNS Recursor application polling module
 * Capable of collecting stats from the agent or via direct connection
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
 * @copyright  2016 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

echo ' powerdns-recrusor';

global $config;
$data = '';

$name = 'powerdns-recursor';
$app_id = $app['app_id'];
if ($agent_data['app'][$name]) {
    $data = $agent_data['app'][$name];
} elseif (isset($config['apps'][$name]['api-key'])) {
    d_echo("\nNo Agent Data. Attempting to connect directly to the powerdns-recursor server " . $device['hostname'] . ":8082\n");
    $context = stream_context_create(array('http' => array('header' => 'X-API-Key: ' . $config['apps'][$name]['api-key'])));
    $data = file_get_contents('http://' . $device['hostname'] . ':8082/servers/localhost/statistics', false, $context);
}

$stats = json_decode($data);

if (!empty($stats)) {
    $rrd_keys = array(
        'all-outqueries' => 'COUNTER',
        'answers-slow' => 'COUNTER',
        'answers0-1' => 'COUNTER',
        'answers1-10' => 'COUNTER',
        'answers10-100' => 'COUNTER',
        'answers100-1000' => 'COUNTER',
        'cache-entries' => 'GAUGE',
        'cache-hits' => 'COUNTER',
        'cache-misses' => 'COUNTER',
        'case-mismatches' => 'COUNTER',
        'chain-resends' => 'COUNTER',
        'client-parse-errors' => 'COUNTER',
        'concurrent-queries' => 'GAUGE',
        'dlg-only-drops' => 'COUNTER',
        'dont-outqueries' => 'COUNTER',
        'edns-ping-matches' => 'COUNTER',
        'edns-ping-mismatches' => 'COUNTER',
        'failed-host-entries' => 'GAUGE',
        'ipv6-outqueries' => 'COUNTER',
        'ipv6-questions' => 'COUNTER',
        'malloc-bytes' => 'GAUGE',
        'max-mthread-stack' => 'GAUGE',
        'negcache-entries' => 'GAUGE',
        'no-packet-error' => 'COUNTER',
        'noedns-outqueries' => 'COUNTER',
        'noerror-answers' => 'COUNTER',
        'noping-outqueries' => 'COUNTER',
        'nsset-invalidations' => 'COUNTER',
        'nsspeeds-entries' => 'GAUGE',
        'nxdomain-answers' => 'COUNTER',
        'outgoing-timeouts' => 'COUNTER',
        'over-capacity-drops' => 'COUNTER',
        'packetcache-entries' => 'GAUGE',
        'packetcache-hits' => 'COUNTER',
        'packetcache-misses' => 'COUNTER',
        'policy-drops' => 'COUNTER',
        'qa-latency' => 'GAUGE',
        'questions' => 'COUNTER',
        'resource-limits' => 'COUNTER',
        'security-status' => 'GAUGE',
        'server-parse-errors' => 'COUNTER',
        'servfail-answers' => 'COUNTER',
        'spoof-prevents' => 'COUNTER',
        'sys-msec' => 'COUNTER',
        'tcp-client-overflow' => 'COUNTER',
        'tcp-clients' => 'GAUGE',
        'tcp-outqueries' => 'COUNTER',
        'tcp-questions' => 'COUNTER',
        'throttle-entries' => 'GAUGE',
        'throttled-out' => 'COUNTER',
        'throttled-outqueries' => 'COUNTER',
        'too-old-drops' => 'COUNTER',
        'unauthorized-tcp' => 'COUNTER',
        'unauthorized-udp' => 'COUNTER',
        'unexpected-packets' => 'COUNTER',
        'unreachables' => 'COUNTER',
        'uptime' => 'COUNTER',
        'user-msec' => 'COUNTER',
    );

    // only the stats we store in rrd
    $fields = array_intersect_key($stats, $rrd_keys);

    $rrd_name = array('app', 'powerdns', 'recursor', $app_id);
    $rrd_def = array();
    foreach ($rrd_keys as $statname => $type) {
        $rrd_def[] = 'DS:' . substr($statname, 0, 19) . ':' . $type . ":600:0:U ";
    }

    $tags = compact('name', 'app_id', 'rrd_name', 'rrd_def');
    data_update($device, 'app', $tags, $fields);
}

unset($data, $stats, $rrd_def, $rrd_name, $rrd_keys, $tags, $fields);
