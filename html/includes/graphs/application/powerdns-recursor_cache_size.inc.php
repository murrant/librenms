<?php
include 'powerdns-recursor.inc.php';

require 'includes/graphs/common.inc.php';

$scale_min = 0;
$colours = 'mixed';
$nototal = 0;
$unit_text = 'Entries';


if (is_file($rrd_filename)) {
    $rrd_list = array(
        array(
            'filename' => $rrd_filename,
            'ds' => 'cache-entries',
            'descr' => 'Query Cache',
            'colour' => '008800FF',
        ),
        array(
            'filename' => $rrd_filename,
            'ds' => 'packetcache-entries',
            'descr' => 'Packet Cache',
            'colour' => '880000FF',
        )
    );
} else {
    echo "file missing: $file";
}

require 'includes/graphs/generic_multi_line.inc.php';
