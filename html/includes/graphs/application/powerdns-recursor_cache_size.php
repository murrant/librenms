<?php
include 'powerdns-recursor.inc.php';

require 'includes/graphs/common.inc.php';

$scale_min    = 0;
$colours      = 'mixed';
$nototal      = 0;
$unit_text    = 'Entries';

$array        = array(
    'cache-entries'  => array(
        'descr'  => 'Query Cache',
        'colour' => '008800FF',
    ),
    'packetcache-entries' => array(
        'descr'  => 'Packet Cache',
        'colour' => '880000FF',
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

require 'includes/graphs/generic_multi_line.inc.php';
