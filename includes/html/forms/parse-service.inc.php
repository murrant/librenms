<?php

/*
 * LibreNMS
 *
 * Copyright (c) 2016 Aaron Daniels <aaron@daniels.id.au>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

use App\Models\Service;

if (! Auth::user()->hasGlobalAdmin()) {
    exit('ERROR: You need to be admin');
}

$service = Service::select([
    'service_type',
    'service_ip',
    'service_desc',
    'service_param',
    'service_ignore',
    'service_disabled',
    'service_template_id',
    'service_name',
])->find(Request::input('service_id'));

header('Content-Type: application/json');
echo json_encode($service, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
