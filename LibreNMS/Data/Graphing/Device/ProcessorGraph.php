<?php

/**
 * ProcessorGraph.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Data\Graphing\Device;

use App\Facades\DeviceCache;
use App\Facades\LibrenmsConfig;
use App\Facades\Rrd;
use App\Models\Device;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Data\Graphing\AbstractGraph;
use LibreNMS\Data\Graphing\Builders\MultiLineGraphBuilder;
use LibreNMS\Data\Graphing\Builders\MultiSimplexSeparatedGraphBuilder;
use LibreNMS\Data\Graphing\GraphParameters;

class ProcessorGraph extends AbstractGraph
{
    public string $type = 'device';
    public string $subtype = 'processor';

    private Device $device;
    private Collection $processors;

    public function __construct(
        private readonly array $vars = [],
    ) {
        $this->device = DeviceCache::get($this->vars['device'] ?? null);
        $this->processors = $this->device->exists ? $this->device->processors : new Collection;
    }

    public function authorize(): bool
    {
        if ($processor = $this->processors->first()) {
            return Gate::allows('view', $processor);
        }

        return $this->device->exists && Gate::allows('view', $this->device);
    }

    public function getGraphTitle(): string
    {
        return $this->device->display ?? '';
    }

    public function getRrdFiles(): array
    {
        $files = [];
        foreach ($this->processors as $proc) {
            $files[] = Rrd::name($this->device->hostname, ['processor', $proc->processor_type, $proc->processor_index]);
        }

        return $files;
    }

    public function definition(GraphParameters $graph_params): array
    {
        if ($this->processors->isEmpty()) {
            throw new \LibreNMS\Exceptions\RrdGraphException('No Processors');
        }

        // Filter valid datasets and run checkRrdExists exactly once per processor
        $valid_datasets = [];
        foreach ($this->processors as $proc) {
            $rrd_filename = Rrd::name($this->device->hostname, ['processor', $proc->processor_type, $proc->processor_index]);
            if (Rrd::checkRrdExists($rrd_filename)) {
                $valid_datasets[] = [
                    'filename' => $rrd_filename,
                    'descr' => $proc->getFormattedDescription(),
                ];
            }
        }

        $rrd_count = count($valid_datasets);

        if (LibrenmsConfig::getOsSetting($this->device->os, 'processor_stacked')) {
            $builder = (new MultiSimplexSeparatedGraphBuilder())
                ->unitText('Load %')
                ->units('%')
                ->totalUnits('%')
                ->colours('oranges')
                ->scaleMin(0)
                ->scaleMax(100)
                ->divider(max(1, $rrd_count))
                ->textOrig()
                ->noTotal();

            foreach ($valid_datasets as $dataset) {
                $builder->addDataset($dataset['filename'], 'usage', $dataset['descr']);
            }

            return $builder->build($graph_params);
        }

        $builder = (new MultiLineGraphBuilder())
            ->unitText('Load %')
            ->units('')
            ->totalUnits('%')
            ->colours('mixed')
            ->scaleMin(0)
            ->scaleMax(100)
            ->noTotal();

        foreach ($valid_datasets as $dataset) {
            $builder->addDataset($dataset['filename'], 'usage', $dataset['descr'], area: true);
        }

        return $builder->build($graph_params);
    }
}
