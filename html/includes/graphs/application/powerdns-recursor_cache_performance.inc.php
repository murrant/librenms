<?php
/**
 * powerdns-recursor_packetcache.inc.php
 *
 * -Description-
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
include 'powerdns-recursor.inc.php';
require 'includes/graphs/common.inc.php';

$scale_min    = 0;
$colours      = 'mixed';
$nototal      = 0;
$unit_text    = 'Packets/sec';

$array        = array(
    'cache-hits' => array(
        'descr'  => 'Query Cache Hits',
        'colour' => '297159',
    ),
    'cache-misses'  => array(
        'descr'  => 'Query Cache Misses',
        'colour' => '73AC61',
    ),
    'packetcache-hits' => array(
        'descr'  => 'Packet Query Cache Hits',
        'colour' => 'BC7049',
    ),
    'packetcache-misses'  => array(
        'descr'  => 'Packet Query Cache Misses',
        'colour' => 'C98F45',
    ),
);

$i = 0;

if (is_file($rrd_filename)) {
    foreach ($array as $ds => $vars) {
        $rrd_list[$i]['filename'] = $rrd_filename;
        $rrd_list[$i]['descr']    = $vars['descr'];
        $rrd_list[$i]['ds']       = $ds;
        $rrd_list[$i]['colour']   = $vars['colour'];
        $i++;
    }
}
else {
    echo "file missing: $file";
}

require 'includes/graphs/generic_multi_simplex_seperated.inc.php';