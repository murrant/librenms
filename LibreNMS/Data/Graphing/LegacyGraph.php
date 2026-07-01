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
use LibreNMS\Exceptions\InvalidGraph;
use function base_path;

class LegacyGraph extends AbstractGraph
{
    private readonly string $auth_file;
    private readonly string $graph_file;

    private ?string $pageTitle = null;
    private ?string $graphTitle = null;
    private ?bool $authorized = null;
    private array $rrdFiles = [];
    private bool $loaded = false;
    private array $rrdOptions = [];
    private ?Device $device = null;
    private ?Port $port = null;

    /**
     * @param  array<string, scalar>  $vars
     *
     * @throws InvalidGraph
     */
    public function __construct(
        public readonly string $type,
        public readonly string $subtype,
        private readonly array $vars = [],
    ) {
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

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        include_once base_path('includes/common.php');
        include_once base_path('includes/html/functions.inc.php');
        include_once base_path('includes/dbFacile.php');
        include_once base_path('includes/rewrites.php');

        if (! $this->device && isset($this->vars['device'])) {
            $this->device = DeviceCache::get($this->vars['device']);
        }

        if ($this->device) {
            DeviceCache::setPrimary($this->device->device_id);
        }

        if (! $this->port && isset($this->vars['id']) && $this->type === 'port') {
            $this->port = \App\Facades\PortCache::get($this->vars['id']);
        }

        // Local scope variables for the included files
        $device = $this->device;
        $port = $this->port;
        $vars = $this->vars;

        $auth = auth()->guest();
        include $this->auth_file;

        $this->authorized = $auth;
        $this->graphTitle = $graph_title ?? $this->graphTitle;
        $this->pageTitle = $title ?? $this->graphTitle;

        if (! $auth) {
            $this->loaded = true;

            return;
        }

        $graph_params = app()->bound(GraphParameters::class) ? app(GraphParameters::class) : new GraphParameters($this->vars);
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

        $this->rrdOptions = $rrd_options;

        if (isset($rrd_list) && is_array($rrd_list)) {
            $this->rrdFiles = array_column($rrd_list, 'filename');
        } elseif (isset($rrd_filenames) && is_array($rrd_filenames)) {
            $this->rrdFiles = $rrd_filenames;
        } else {
            $this->rrdFiles = isset($rrd_filename) ? [$rrd_filename] : [];
        }

        $this->loaded = true;
    }

    public function authorize(): bool
    {
        $this->load();

        return $this->authorized;
    }

    public function definition(GraphParameters $graph_params): array
    {
        $this->load();

        return $this->rrdOptions;
    }

    public function getPageTitle(): string
    {
        $this->load();

        return $this->pageTitle ?? $this->getGraphTitle();
    }

    public function getGraphTitle(): string
    {
        $this->load();

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
        $this->load();

        return $this->rrdFiles;
    }
}
