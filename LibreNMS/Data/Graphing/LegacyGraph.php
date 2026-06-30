<?php
/**
 * LegacyGraph.php
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

namespace LibreNMS\Data\Graphing;

use App\Facades\DeviceCache;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use LibreNMS\Exceptions\InvalidGraph;
use LibreNMS\Interfaces\Data\Graphing\GraphInterface;

class LegacyGraph implements GraphInterface
{
    private readonly string $auth_file;
    private readonly string $graph_file;

    private ?string $pageTitle = null;
    private ?string $graphTitle = null;
    private ?bool $authorized = null;
    private array $rrdFiles = [];

    public function __construct(
        public readonly string $type,
        public readonly string $subtype,
        private ?Device $device = null,
        private ?Port $port = null,
    ) {
        $this->device ??= DeviceCache::getPrimary();

        $this->auth_file = base_path("includes/html/graphs/$this->type/auth.inc.php");
        $graph_file = base_path("includes/html/graphs/$this->type/$this->subtype.inc.php");
        if (! file_exists($graph_file)) {
            $graph_file = base_path("includes/html/graphs/$this->type/generic.inc.php");
        }
        $this->graph_file = $graph_file;

        if (! file_exists($this->auth_file) || ! file_exists($this->graph_file)) {
            throw new InvalidGraph;
        }
    }

    public function authorize(): bool
    {
        if ($this->authorized === null) {
            $this->legacyIncludes();
            auth()->setUser(User::firstWhere('username', 'murrant')); // FIXME
            $device = $this->device;

            $auth = false;
            include $this->auth_file;

            $this->authorized = $auth;
            $this->graphTitle = $graph_title ?? $this->graphTitle;
            $this->pageTitle = $title ?? $this->graphTitle;

            $unhandled = array_diff(array_keys(get_defined_vars()), ['auth', 'device', 'title', 'graph_title']);
            if ($unhandled) {
                dd($unhandled);
            }
        }

        return $this->authorized;
    }

    public function validation(): array
    {
        return [];
    }

    public function definition(array $vars = []): array
    {
        $this->legacyIncludes();

        // set up "global" variables
        $device = $this->device;
        $graph_title = $this->graphTitle;
        $graph_params = new GraphParameters($vars);
        $type = $graph_params->type;
        $subtype = $graph_params->subtype;
        $height = $graph_params->height;
        $width = $graph_params->width;
        $from = $graph_params->from;
        $to = $graph_params->to;
        $period = $graph_params->period;
        $prev_from = $graph_params->prev_from;
        $inverse = $graph_params->inverse;
        $in = $graph_params->in;
        $out = $graph_params->out;
        $float_precision = $graph_params->float_precision;
        $title = $graph_params->visible('title');
        $nototal = ! $graph_params->visible('total');
        $nodetails = ! $graph_params->visible('details');
        $noagg = ! $graph_params->visible('aggregate');

        $rrd_options = [];

        include $this->graph_file;

        if (isset($rrd_list) && is_array($rrd_list)) {
            $this->rrdFiles = array_column($rrd_list, 'filename');
        } elseif (isset($rrd_filenames) && is_array($rrd_filenames)) {
            $this->rrdFiles = $rrd_filenames;
        } else {
            $this->rrdFiles = isset($rrd_filename) ? [$rrd_filename] : [];
        }

//        $unhandled = array_diff(array_keys(get_defined_vars()), [
//            'vars',
//            'device',
//            'graph_title',
//            'graph_params',
//            'type',
//            'subtype',
//            'height',
//            'width',
//            'from',
//            'to',
//            'period',
//            'prev_from',
//            'inverse',
//            'in',
//            'out',
//            'float_precision',
//            'title',
//            'nototal',
//            'nodetails',
//            'noagg',
//            'rrd_options',
//            'rrd_filename',
//        ]);
//        if ($unhandled) {
//            dd($unhandled);
//        }

        return $rrd_options;
    }

    public function getPageTitle(): string
    {
        return $this->pageTitle ?? $this->getGraphTitle();
    }

    public function getGraphTitle(): string
    {
        if ($this->graphTitle !== null) {
            return $this->graphTitle;
        }

        if ($this->port) {
            return $this->port->device?->display . ' :: ' . $this->port->getDescription();
        }

        return $this->device->display ?? '';
    }

    public function getRrdFiles(): array
    {
        return $this->rrdFiles;
    }

    private function legacyIncludes(): void
    {
        include_once base_path('includes/common.php');
        include_once base_path('includes/html/functions.inc.php');
        include_once base_path('includes/dbFacile.php');
        include_once base_path('includes/rewrites.php');
    }
}
