<?php

$name = 'cape';
$app_id = $app['app_id'];
$unit_text = 'Anti Issues Per Run';
$colours = 'psychedelic';
$dostack = 0;
$printtotal = 1;
$addarea = 0;
$transparency = 15;
$float_precision = 3;

$vars['stddev'] = ($vars['stddev'] ?? 'off') === 'on' ? 'on' : 'off';

if (isset($vars['package'])) {
    $rrd_filename = Rrd::name($device['hostname'],
        ['app', $name, $app['app_id'], 'pkg-anti_issues___-___', $vars['package']]);
} else {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app['app_id'], 'anti_issues']);
}

$rrd_list = [
    [
        'filename' => $rrd_filename,
        'descr' => 'Min',
        'ds' => 's0anti_issues',
    ],
    [
        'filename' => $rrd_filename,
        'descr' => 'Max',
        'ds' => 's1anti_issues',
    ],
    [
        'filename' => $rrd_filename,
        'descr' => 'Mean',
        'ds' => 's3anti_issues',
    ],
    [
        'filename' => $rrd_filename,
        'descr' => 'Median',
        'ds' => 's4anti_issues',
    ],
    [
        'filename' => $rrd_filename,
        'descr' => 'Mode',
        'ds' => 's5anti_issues',
    ],
];

if (isset($vars['stddev']) && $vars['stddev'] == 'on') {
    $rrd_list[] = [
        'filename' => $rrd_filename,
        'descr' => 'StdDev',
        'ds' => 's7anti_issues',
    ];
    $rrd_list[] = [
        'filename' => $rrd_filename,
        'descr' => 'StdDevP',
        'ds' => 's9anti_issues',
    ];
}

require 'includes/html/graphs/generic_multi_line.inc.php';
