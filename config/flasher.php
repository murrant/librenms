<?php

return [
    'default' => 'template',

    'root_script' => '', // 'https://cdn.jsdelivr.net/npm/@flasher/flasher@0.2.2/dist/flasher.min.js',

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
            'tailwindcss' => [
                'view' => 'flasher::tailwindcss',
                'styles' => [
                    'https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.11/base.min.css',
                    'https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.11/utilities.css',
                ],
            ],
            'tailwindcss_bg' => [
                'view' => 'flasher::tailwindcss_bg',
                'styles' => [
                    'https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.11/base.min.css',
                    'https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.11/utilities.css',
                ],
            ],
            'bootstrap' => [
                'view' => 'flasher::bootstrap',
                'styles' => [
                    'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.1.1/css/bootstrap.min.css',
                ],
            ],
        ],
    ],

    'auto_create_from_session' => true,

    'types_mapping' => [
        'success' => ['success'],
        'error' => ['error', 'danger'],
        'warning' => ['warning', 'alarm'],
        'info' => ['info', 'notice', 'alert'],
    ],

    'observer_events' => [
        'exclude' => [
            'forceDeleted',
            'restored',
        ],
    ],
];
