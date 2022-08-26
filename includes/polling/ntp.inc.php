<?php
/*
 * LibreNMS module to capture NTP statistics
 *
 * Copyright (c) 2016 Aaron Daniels <aaron@daniels.id.au>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 *
 * This module will display NTP details from various device types.
 * To display, modules must create rrd's named: ntp-%PEER%.rrd with the following DS':
 *      DS:stratum:GAUGE:'.\LibreNMS\Config::get('rrd.heartbeat').':0:U
 *      DS:offset:GAUGE:'.\LibreNMS\Config::get('rrd.heartbeat').':0:U
 *      DS:delay:GAUGE:'.\LibreNMS\Config::get('rrd.heartbeat').':0:U
 *      DS:dispersion:GAUGE:'.\LibreNMS\Config::get('rrd.heartbeat').':0:U
 */

use LibreNMS\OS;

if (! $os instanceof OS) {
    $os = OS::make($device);
}
(new \LibreNMS\Modules\Ntp())->poll($os);
