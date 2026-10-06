<?php

namespace App\Graphing;

use App\Models\Device;
use App\Models\Port;

/**
 * The entities a graph is about, resolved once per graph
 */
class GraphSubject
{
    public function __construct(
        public readonly ?Device $device = null,
        public readonly ?Port $port = null,
    ) {
    }
}
