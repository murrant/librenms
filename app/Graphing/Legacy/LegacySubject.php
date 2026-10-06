<?php

namespace App\Graphing\Legacy;

use App\Graphing\GraphParameters;
use App\Graphing\GraphSubject;
use App\Models\Device;
use App\Models\Port;

/**
 * Result of running a legacy auth.inc.php file. The runner keeps the auth file's
 * variable scope alive for the graph template, as legacy templates depend on it.
 */
class LegacySubject extends GraphSubject
{
    public function __construct(
        ?Device $device,
        ?Port $port,
        public readonly bool $authorized,
        public readonly LegacyTemplateRunner $runner,
        public readonly GraphParameters $params,
        public readonly ?string $subtitle,
        public readonly ?string $graphTitle,
    ) {
        parent::__construct($device, $port);
    }
}
