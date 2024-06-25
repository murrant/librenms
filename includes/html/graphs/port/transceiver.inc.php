<?php

use App\Models\Port;


/** @var Port $port */
/** @var \App\Models\Device $device */


$port = $port instanceof Port ? $port : Port::find($port['port_id']);
$rrd_list = [];

//    dd(array_keys(get_defined_vars()));
foreach ($port->transceivers as $transceiver) {
    foreach ($transceiver->metrics as $metric) {
        $rrd_filename = Rrd::name($device['hostname'], ['transceiver', $metric->type, $transceiver->index, $metric->channel]);
        if ((empty($metric_type) || $metric->type == $metric_type) && Rrd::checkRrdExists($rrd_filename)) {
            $rrd_list[] = [
                'filename' => $rrd_filename,
                'descr' => trans_choice('port.transceivers.metrics.' . $metric->type, $metric->channel, ['channel' => $metric->channel]),
                'ds' => 'value',
            ];
        }
    }
}

$colours = 'mixed';
$nototal = 1;
$unit_text = empty($metric_type) ? '' : __('port.transceivers.units.' . $metric_type);
$divider = 1;
//$scale_min = 0;

require 'includes/html/graphs/generic_v3_multiline_float.inc.php';
