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
        'port' => 'Port',
        'port_association_mode' => 'Port Association',
        'serial' => 'Serial',
        'sysName' => 'sysName',
        'transport' => 'Transport',
        'version' => 'OS Version',
        'type' => 'Device type',
    ],
    'options' => [
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
        'ip_family' => [
            'auto' => 'Auto',
            'ipv4' => 'IPv4',
            'ipv6' => 'IPv6',
        ],
        'protocol' => [
            'udp' => 'UDP',
            'tcp' => 'TCP',
        ],
    ],
];
