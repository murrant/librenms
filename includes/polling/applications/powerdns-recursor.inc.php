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

if (!empty($data)) {
    $rrd_def = array(
        'all-outqueries' => 'DS:all-outqueries:COUNTER:600:0:U',
        'answers-slow' => 'DS:answers-slow:COUNTER:600:0:U',
        'answers0-1' => 'DS:answers0-1:COUNTER:600:0:U',
        'answers1-10' => 'DS:answers1-10:COUNTER:600:0:U',
        'answers10-100' => 'DS:answers10-100:COUNTER:600:0:U',
        'answers100-1000' => 'DS:answers100-1000:COUNTER:600:0:U',
        'cache-entries' => 'DS:cache-entries:GAUGE:600:0:U',
        'cache-hits' => 'DS:cache-hits:COUNTER:600:0:U',
        'cache-misses' => 'DS:cache-misses:COUNTER:600:0:U',
        'case-mismatches' => 'DS:case-mismatches:COUNTER:600:0:U',
        'chain-resends' => 'DS:chain-resends:COUNTER:600:0:U',
        'client-parse-errors' => 'DS:client-parse-errors:COUNTER:600:0:U',
        'concurrent-queries' => 'DS:concurrent-queries:GAUGE:600:0:U',
        'dlg-only-drops' => 'DS:dlg-only-drops:COUNTER:600:0:U',
        'dont-outqueries' => 'DS:dont-outqueries:COUNTER:600:0:U',
        'edns-ping-matches' => 'DS:edns-ping-matches:COUNTER:600:0:U',
        'edns-ping-mismatches' => 'DS:edns-ping-mismatches:COUNTER:600:0:U',
        'failed-host-entries' => 'DS:failed-host-entries:GAUGE:600:0:U',
        'ipv6-outqueries' => 'DS:ipv6-outqueries:COUNTER:600:0:U',
        'ipv6-questions' => 'DS:ipv6-questions:COUNTER:600:0:U',
        'malloc-bytes' => 'DS:malloc-bytes:GAUGE:600:0:U',
        'max-mthread-stack' => 'DS:max-mthread-stack:GAUGE:600:0:U',
        'negcache-entries' => 'DS:negcache-entries:GAUGE:600:0:U',
        'no-packet-error' => 'DS:no-packet-error:COUNTER:600:0:U',
        'noedns-outqueries' => 'DS:noedns-outqueries:COUNTER:600:0:U',
        'noerror-answers' => 'DS:noerror-answers:COUNTER:600:0:U',
        'noping-outqueries' => 'DS:noping-outqueries:COUNTER:600:0:U',
        'nsset-invalidations' => 'DS:nsset-invalidations:COUNTER:600:0:U',
        'nsspeeds-entries' => 'DS:nsspeeds-entries:GAUGE:600:0:U',
        'nxdomain-answers' => 'DS:nxdomain-answers:COUNTER:600:0:U',
        'outgoing-timeouts' => 'DS:outgoing-timeouts:COUNTER:600:0:U',
        'over-capacity-drops' => 'DS:over-capacity-drops:COUNTER:600:0:U',
        'packetcache-entries' => 'DS:packetcache-entries:GAUGE:600:0:U',
        'packetcache-hits' => 'DS:packetcache-hits:COUNTER:600:0:U',
        'packetcache-misses' => 'DS:packetcache-misses:COUNTER:600:0:U',
        'policy-drops' => 'DS:policy-drops:COUNTER:600:0:U',
        'qa-latency' => 'DS:qa-latency:GAUGE:600:0:U',
        'questions' => 'DS:questions:COUNTER:600:0:U',
        'resource-limits' => 'DS:resource-limits:COUNTER:600:0:U',
        'security-status' => 'DS:security-status:GAUGE:600:0:U',
        'server-parse-errors' => 'DS:server-parse-errors:COUNTER:600:0:U',
        'servfail-answers' => 'DS:servfail-answers:COUNTER:600:0:U',
        'spoof-prevents' => 'DS:spoof-prevents:COUNTER:600:0:U',
        'sys-msec' => 'DS:sys-msec:COUNTER:600:0:U',
        'tcp-client-overflow' => 'DS:tcp-client-overflow:COUNTER:600:0:U',
        'tcp-clients' => 'DS:tcp-clients:GAUGE:600:0:U',
        'tcp-outqueries' => 'DS:tcp-outqueries:COUNTER:600:0:U',
        'tcp-questions' => 'DS:tcp-questions:COUNTER:600:0:U',
        'throttle-entries' => 'DS:throttle-entries:GAUGE:600:0:U',
        'throttled-out' => 'DS:throttled-out:COUNTER:600:0:U',
        'throttled-outqueries' => 'DS:throttled-outquerie:COUNTER:600:0:U',
        'too-old-drops' => 'DS:too-old-drops:COUNTER:600:0:U',
        'unauthorized-tcp' => 'DS:unauthorized-tcp:COUNTER:600:0:U',
        'unauthorized-udp' => 'DS:unauthorized-udp:COUNTER:600:0:U',
        'unexpected-packets' => 'DS:unexpected-packets:COUNTER:600:0:U',
        'unreachables' => 'DS:unreachables:COUNTER:600:0:U',
        'uptime' => 'DS:uptime:COUNTER:600:0:U',
        'user-msec' => 'DS:user-msec:COUNTER:600:0:U',
    );

    //decode and flatten the data
    $stats = array();
    foreach (json_decode($data, true) as $stat) {
        $stats[$stat['name']] = $stat['value'];
    }
    d_echo($stats);

    // only the stats we store in rrd
    $fields = array();
    foreach ($rrd_def as $key => $value) {
        if (isset($stats[$key])) {
            $fields[$key] = $stats[$key];
        } else {
            $fields[$key] = 'U';
        }
    }

    $rrd_name = array('app', 'powerdns', 'recursor', $app_id);
    $tags = compact('name', 'app_id', 'rrd_name', 'rrd_def');
    data_update($device, 'app', $tags, $fields);
}

unset($data, $stats, $rrd_def, $rrd_name, $rrd_keys, $tags, $fields);
