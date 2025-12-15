<?php

return [
    'load' => [
        'options' => [
            'warning' => [
                'help' => 'Exit with WARNING status if load average exceeds WLOADn'
            ],
            'critical' => [
                'help' => 'Exit with CRITICAL status if load average exceeds CLOADn'
            ],
            'percpu' => [
                'help' => 'Divide the load averages by the number of CPUs (when possible)'
            ],
            'procs-to-show' => [
                'help' => "Number of processes to show when printing the top consuming processes.\nNUMBER_OF_PROCS=0 disables this feature. Default value is 0"
            ]
        ],
    ],
];
