<?php

return [
    'attributes' => [
        'authname' => 'Auth User',
        'authpass' => 'Auth Password',
        'cryptopass' => 'Crypto Key',
        'community' => 'SNMP Community',
        'display' => 'Display Name',
        'features' => 'OS Features',
        'hardware' => 'Hardware',
        'hostname' => 'Hostname or IP',
        'icon' => 'Icon',
        'location' => 'Location',
        'os' => 'Device OS',
        'serial' => 'Serial',
        'sysName' => 'sysName',
        'version' => 'OS Version',
        'type' => 'Device type',
    ],
    'add' => [
        'types' => [
            'v1' => 'SNMP v1',
            'v2c' => 'SNMP v2c',
            'v3' => 'SNMP v3',
            'ping' => 'Ping',
        ],
        'v3auth' => [
            'noAuthNoPriv' => 'None',
            'authNoPriv' => 'Password',
            'authPriv' => 'Encrypted',
        ],
    ],
];
