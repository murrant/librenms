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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Data\Graphing\AbstractGraph;
use LibreNMS\Data\Graphing\Builders\MultiLineGraphBuilder;
use LibreNMS\Data\Graphing\Builders\MultiSimplexSeparatedGraphBuilder;
use LibreNMS\Exceptions\RrdGraphException;
use LibreNMS\Util\Rewrite;

class ProcessorGraph extends AbstractGraph
{
    public string $type = 'device';
    public string $subtype = 'processor';

    private Device $device;


    public function __construct(private readonly array $vars = [])
    {
        $this->device = DeviceCache::get($this->vars['device'] ?? null);
    }

    public function authorize(): bool
    {
        return Gate::allows('view', $this->device);
    }

    public function getGraphTitle(): string
    {
        return $this->device->display ?? '';
    }

    public function getRrdFiles(): array
    {
        $procs = DB::table('processors')->where('device_id', $this->device->device_id)->get();
        $files = [];
        foreach ($procs as $proc) {
            $rrd_filename = Rrd::name($this->device->hostname, ['processor', $proc->processor_type, $proc->processor_index]);
            if (Rrd::checkRrdExists($rrd_filename)) {
                $files[] = $rrd_filename;
            }
        }
        return $files;
    }

    public function definition(array $vars = []): array
    {
        $this->authorize();

        $procs = DB::table('processors')->where('device_id', $this->device->device_id)->get();
        if ($procs->isEmpty()) {
            throw new RrdGraphException('No Processors');
        }

        $rrd_list = [];
        foreach ($procs as $proc) {
            $rrd_filename = Rrd::name($this->device->hostname, ['processor', $proc->processor_type, $proc->processor_index]);
            if (Rrd::checkRrdExists($rrd_filename)) {
                $descr = Rewrite::shortHrDevice($proc->processor_descr);
                $rrd_list[] = [
                    'filename' => $rrd_filename,
                    'descr' => $descr,
                    'ds' => 'usage',
                ];
            }
        }

        $vars = array_merge($this->vars, $vars);

        // Check if stacked processor graph is configured for this OS
        if (LibrenmsConfig::getOsSetting($this->device->os, 'processor_stacked')) {
            $builder = (new MultiSimplexSeparatedGraphBuilder())
                ->unitText('Load %')
                ->units('%')
                ->totalUnits('%')
                ->colours('oranges')
                ->scaleMin(0)
                ->scaleMax(100)
                ->divider(count($rrd_list))
                ->textOrig()
                ->noTotal();

            foreach ($rrd_list as $rrd) {
                $builder->addDataset(
                    filename: $rrd['filename'],
                    ds: $rrd['ds'],
                    description: $rrd['descr']
                );
            }

            return $builder->build($vars);
        }

        $builder = (new MultiLineGraphBuilder())
            ->unitText('Load %')
            ->units('')
            ->totalUnits('%')
            ->colours('mixed')
            ->scaleMin(0)
            ->scaleMax(100)
            ->noTotal();

        foreach ($rrd_list as $rrd) {
            $builder->addDataset(
                filename: $rrd['filename'],
                ds: $rrd['ds'],
                description: $rrd['descr'],
                area: true
            );
        }

        return $builder->build($vars);
    }
}
