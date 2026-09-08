<?php

namespace App\Listeners;

use App\Events\SnmpQueryExecuted;
use App\Models\Eventlog;
use Illuminate\Support\Facades\Log;
use LibreNMS\Enum\Severity;
use LibreNMS\Util\Debug;

class CheckSnmpExitCode
{
    public function handle(SnmpQueryExecuted $event): void
    {
        $debugInfo = $event->debugInfo;
        $exitCode = $debugInfo?->getExitCode();

        if (! $exitCode) {
            return;
        }

        $stderr = $debugInfo?->getStderr() ?? '';

        if (str_starts_with($stderr, 'Invalid authentication protocol specified')) {
            Eventlog::log('Unsupported SNMP authentication algorithm - ' . $exitCode, $event->device, 'poller', Severity::Error);
        } elseif (str_starts_with($stderr, 'Invalid privacy protocol specified')) {
            Eventlog::log('Unsupported SNMP privacy algorithm - ' . $exitCode, $event->device, 'poller', Severity::Error);
        }

        if (Debug::isEnabled()) {
            Log::debug('Exitcode: ' . $exitCode, [$stderr]);
        }
    }
}
