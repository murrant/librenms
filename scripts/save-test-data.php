#!/usr/bin/env php
<?php

fwrite(STDERR, "\033[31mThis script is deprecated and will be removed in a future release. Use lnms dev:save instead.\033[0m\n\n");

$install_dir = realpath(__DIR__ . '/..');
chdir($install_dir);

$options = getopt(
    'o:v:m:nf:dh',
    [
        'os:',
        'variant:',
        'modules:',
        'no-save',
        'file:',
        'debug',
        'help',
    ]
);

$lnms = $install_dir . DIRECTORY_SEPARATOR . 'lnms';
$cmd = [$lnms, 'dev:save'];

// Help or no relevant args: show command help
$hasHelp = isset($options['h']) || isset($options['help']);
$hasOs = isset($options['o']) || isset($options['os']);
$hasModules = isset($options['m']) || isset($options['modules']);

if ($hasHelp || (!$hasOs && !$hasModules)) {
    $cmd[] = '--help';
} else {
    // OS + variant
    $os = $options['o'] ?? $options['os'] ?? null;
    $variant = $options['v'] ?? $options['variant'] ?? null;
    if (!empty($os)) {
        $cmd[] = $variant ? "{$os}_{$variant}" : $os;
    }

    // Modules
    $modules = $options['m'] ?? $options['modules'] ?? null;
    if (! empty($modules)) {
        array_push($cmd, '-m', $modules);
    }

    // No-save
    if (isset($options['n']) || isset($options['no-save'])) {
        $cmd[] = '--no-save';
    }

    // File
    $file = $options['f'] ?? $options['file'] ?? null;
    if (!empty($file)) {
        array_push($cmd, '-f', $file);
    }

    // Debug
    if (isset($options['d']) || isset($options['debug'])) {
        $cmd[] = '-d';
    }
}

$escaped = implode(' ', array_map(escapeshellarg(...), $cmd));
passthru($escaped, $exitCode);
exit($exitCode);
