<?php

namespace App\Graphing;

use App\Models\Device;
use App\Models\Port;

/**
 * Metadata about a resolved graph, used by pages that display graphs
 */
final readonly class GraphDescription
{
    public function __construct(
        public string $title,
        public ?string $subtitle = null,
        public ?Device $device = null,
        public ?Port $port = null,
    ) {
    }
}
