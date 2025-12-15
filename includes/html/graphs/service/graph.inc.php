<?php

/*
 * LibreNMS module to display graphing for Nagios Service
 *
 * Copyright (c) 2016 Aaron Daniels <aaron@daniels.id.au>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

/** @var array $device */
/** @var array $vars */
/** @var \LibreNMS\Data\Graphing\GraphParameters $graph_params */

use LibreNMS\Exceptions\RrdGraphException;
use LibreNMS\Services\ServiceFactory;

$service = DeviceCache::get($device['device_id'])->services()->when($vars['id'] ?? null, fn ($q) => $q->where('service_id', $vars['id']))->first();

if ($service === null) {
    throw new RrdGraphException('Service not found', 'No Service');
}

$rrd_filename = Rrd::name($device['hostname'], ['services', $service->service_id]);

include 'includes/html/graphs/common.inc.php';
$graph_params->scale_min = 0;
$graph_params->sloped = true;

$rrd_options[] = 'COMMENT:                      Now     Avg      Max\\n';

$check = ServiceFactory::make($service->service_type);
foreach ($check->dataSets($rrd_filename, $vars['ds'] ?? null) as $ds) {
    $rrd_options = array_merge($rrd_options, $ds->graphCommands);
}
