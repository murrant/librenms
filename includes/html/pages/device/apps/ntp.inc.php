<?php
/*
 * LibreNMS module to capture statistics from the CISCO-NTP-MIB
 *
 * Copyright (c) 2016 Aaron Daniels <aaron@daniels.id.au>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */


?>
<table id='table' class='table table-condensed table-responsive table-striped'>
    <thead>
    <tr>
        <th>Peer</th>
        <th>Stratum</th>
        <th>Peer Reference</th>
        <th>Status</th>
    </tr>
    </thead>
<?php
foreach ($app->data as $peer) {
    if ($peer['status'] == 2) {
        $status = $peer['error'];
        $error_class = 'class="danger"';
    } else {
        $status = 'Ok';
        $error_class = '';
    } ?>
<tr <?php echo $error_class; ?>>
<td><?php echo htmlentities($peer['label'] ?? $peer['peer'] . ':' . $peer['port']) ?></td>
<td><?php echo htmlentities($peer['stratum']); ?></td>
<td><?php echo htmlentities($peer['peerref']); ?></td>
<td><?php echo htmlentities($status); ?></td>
</tr>
    <?php
}
?>
</table>

<div class="panel panel-default" id="stratum">
    <div class="panel-heading">
        <h3 class="panel-title">NTP Stratum</h3>
    </div>
    <div class="panel-body">
        <?php

        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['to'] = \LibreNMS\Config::get('time.now');
        $graph_array['type'] = 'device_ntp_stratum';
        require 'includes/html/print-graphrow.inc.php';

        ?>
    </div>
</div>

<div class="panel panel-default" id="offset">
    <div class="panel-heading">
        <h3 class="panel-title">Offset</h3>
    </div>
    <div class="panel-body">
        <?php

        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['to'] = \LibreNMS\Config::get('time.now');
        $graph_array['type'] = 'device_ntp_offset';
        require 'includes/html/print-graphrow.inc.php';

        ?>
    </div>
</div>

<div class="panel panel-default" id="delay">
    <div class="panel-heading">
        <h3 class="panel-title">Delay</h3>
    </div>
    <div class="panel-body">
        <?php

        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['to'] = \LibreNMS\Config::get('time.now');
        $graph_array['type'] = 'device_ntp_delay';
        require 'includes/html/print-graphrow.inc.php';

        ?>
    </div>
</div>

<div class="panel panel-default" id="dispersion">
    <div class="panel-heading">
        <h3 class="panel-title">Dispersion</h3>
    </div>
    <div class="panel-body">
        <?php

        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['to'] = \LibreNMS\Config::get('time.now');
        $graph_array['type'] = 'device_ntp_dispersion';
        require 'includes/html/print-graphrow.inc.php';

        ?>
    </div>
</div>
