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
                    'timeout' => 8000,
                    'y_offset' => '55px',
                ],
            ],
        ],
    ],
];
