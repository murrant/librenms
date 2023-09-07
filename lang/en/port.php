<?php

return [
    'no_data' => 'No update requested',
    'groups' => [
        'updated' => ':port: groups updated',
        'none' => ':port no update requested',
    ],
    'ifSpeed' => [
        'ifName_missing' => 'ifName is required to update port speed',
        'cleared' => ':name Port speed override cleared',
        'updated' => ':name Port speed override set to :rate',
        'no_change' => ':name Port speed not changed',
    ],
    'ifName' => [
        'ifName_missing' => 'ifName is required to update port description',
        'cleared' => ':name Port ifAlias cleared manually',
        'updated' => ':name Port ifAlias set manually: :value',
        'no_change' => ':name Port descr not changed'
    ],
    'ifName_tune' => [
        'ifName_missing' => 'ifName is required to set port tuning',
        'cleared' => ':name Port tuning disabled',
        'updated' => ':name Port tuning enabled',
        'no_change' => ':name Port tuning not changed',
    ],
    'disabled' => [
        'enabled' => 'Port :name polling enabled',
        'disabled' => 'Port :name polling disabled',
    ],
    'ignore' => [
        'enabled' => 'Ignore port :name',
        'disabled' => 'Do not ignore port :name',
    ],
    'tuned' => 'Port :name rrd restricted to max rate of :rate',
];
