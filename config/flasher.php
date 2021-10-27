<?php

return [
    'default' => 'template.librenms',

    'root_script' => null,

    'template_factory' => [
        'default' => 'librenms',
        'templates' => [
            'librenms' => [
                'view' => 'layouts.flasher-notification',
                'options' => [
                    'timeout' => 800000,
                    'style' => [
                        'top' => '55px',
                    ],
                ],
            ],
        ],
    ],
];
