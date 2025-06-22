<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\CompactToVariablesRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/LibreNMS',
        __DIR__ . '/app',
        __DIR__ . '/html',
        __DIR__ . '/includes',
        __DIR__ . '/tests',
    ])
    ->withRules([
        CompactToVariablesRector::class,
        \LibreNMS\Rector\ConvertDatastorePutCalls::class,
    ]);
