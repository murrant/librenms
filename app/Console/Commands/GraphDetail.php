<?php

namespace App\Console\Commands;

use App\Facades\DeviceCache;
use App\Models\Device;
use Illuminate\Console\Command;
use LibreNMS\Data\Graphing\GraphFactory;

class GraphDetail extends Command
{
    protected $signature = 'graph:detail {name} {--device=}';
    protected $description = 'Show info about a given graph';

    public function handle(GraphFactory $graphs): int
    {
        $device = DeviceCache::get($this->option('device') ?: Device::limit(1)->value('device_id'));

        $graph = $graphs->graphFor($this->argument('name'), [
            'type' => $this->argument('name'),
            'device' => $device->device_id,
        ]);

        $this->line("Type: $graph->type");
        $this->line("Subtype: $graph->subtype");
        $this->line('Authorized: ' . ($graph->authorize() ? 'true' : 'false'));
        $this->line("Graph Title: " . $graph->getGraphTitle());
        $this->line("Page Title: " . substr($graph->getPageTitle(), 0, 80));
        $this->line("Rrd Files: " . implode(', ', array_map(fn($f) => basename($f), $graph->getRrdFiles())));
        $this->line("Definition: " . substr(implode(' ', $graph->definition()), 80));


        return 0;
    }
}
