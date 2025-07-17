<?php

namespace App\Observers;

use App\Models\Eventlog;
use App\Models\Processor;
use LibreNMS\Enum\Severity;

class ProcessorObserver
{
    public function created(Processor $processor): void
    {
        $message = "Processor Discovered: {$processor->processor_type} {$processor->processor_index} {$processor->processor_descr}";
        Eventlog::log($message, $processor->device_id, 'processors', Severity::Notice, $processor->processor_id);
    }

    public function deleted(Processor $processor): void
    {
        $message = "Processor Removed: {$processor->processor_type} {$processor->processor_index} {$processor->processor_descr}";
        Eventlog::log($message, $processor->device_id, 'processors', Severity::Notice, $processor->processor_id);
    }
}
