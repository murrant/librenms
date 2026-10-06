<?php

/**
 * LegacyGraphHandler.php
 *
 * Runs legacy includes/html/graphs templates. The variable contract with the
 * templates is kept identical to the original implementation, templates are not changed.
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

namespace App\Graphing\Legacy;

use Amenadiel\JpGraph\Graph\Graph as JpGraph;
use App\Facades\DeviceCache;
use App\Facades\PortCache;
use App\Graphing\Contracts\GraphHandler;
use App\Graphing\Contracts\RenderPlan;
use App\Graphing\Exceptions\GraphRenderFailed;
use App\Graphing\GraphAccess;
use App\Graphing\GraphDescription;
use App\Graphing\GraphImage;
use App\Graphing\GraphQuery;
use App\Graphing\GraphSubject;
use App\Graphing\Plans\PrebuiltImage;
use App\Graphing\Plans\RrdCommand;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Support\Facades\Auth;
use LibreNMS\Enum\ImageFormat;
use LibreNMS\Util\Debug;
use LogicException;

class LegacyGraphHandler implements GraphHandler
{
    private const BASE_DIR = 'includes/html/graphs';

    public function __construct(
        private readonly string $type,
        private readonly string $subtype,
    ) {
    }

    public static function exists(string $type, string $subtype): bool
    {
        if (! self::validName($type) || ! self::validName($subtype)) {
            return false;
        }

        return is_file(self::path($type, 'auth')) && self::templateFile($type, $subtype) !== null;
    }

    /**
     * @return string[]
     */
    public static function subtypes(string $type): array
    {
        $dir = base_path(self::BASE_DIR . '/' . basename($type));
        $types = [];

        if (is_dir($dir)) {
            foreach (new \DirectoryIterator($dir) as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.inc.php')) {
                    $name = $file->getBasename('.inc.php');
                    if ($name !== 'auth') {
                        $types[] = $name;
                    }
                }
            }
        }

        return $types;
    }

    public function subject(GraphQuery $query, GraphAccess $access): LegacySubject
    {
        // legacy permission checks read the logged in user
        if ($access->user !== null && ! $access->user->is(Auth::user())) {
            throw new LogicException('Legacy graphs can only be rendered for the logged in user');
        }

        [$device, $port] = $this->requestEntities($query);

        if ($device?->exists) {
            DeviceCache::setPrimary($device->device_id);
        }

        // variables for included graphs
        $graph_params = $query->parameters();
        $scope = [
            'vars' => $query->vars,
            'device' => $device,
            'port' => $port,
            'graph_params' => $graph_params,
            'type' => $graph_params->type,
            'subtype' => $graph_params->subtype,
            'height' => $graph_params->height,
            'width' => $graph_params->width,
            'from' => $graph_params->from,
            'to' => $graph_params->to,
            'period' => $graph_params->period,
            'prev_from' => $graph_params->prev_from,
            'inverse' => $graph_params->inverse,
            'in' => $graph_params->in,
            'out' => $graph_params->out,
            'float_precision' => $graph_params->float_precision,
            'title' => $graph_params->visible('title'),
            'nototal' => ! $graph_params->visible('total'),
            'nodetails' => ! $graph_params->visible('details'),
            'noagg' => ! $graph_params->visible('aggregate'),
            'graph' => null,
            'rrd_options' => [],
            'rrd_filename' => null,
            // trusted contexts were authenticated by signed url, allow_unauth_graphs, or are internal
            'auth' => $access->isTrusted(),
        ];

        $runner = new LegacyTemplateRunner(self::path($this->type, 'auth'), $scope);
        $scope = $runner->auth();

        return new LegacySubject(
            device: $this->resolveDevice($device, $scope['device'] ?? null),
            port: $port ?? (($scope['port'] ?? null) instanceof Port ? $scope['port'] : null),
            authorized: (bool) ($scope['auth'] ?? false),
            runner: $runner,
            params: $graph_params,
            subtitle: isset($scope['title']) && is_string($scope['title']) && $scope['title'] !== '' ? $scope['title'] : null,
            graphTitle: isset($scope['graph_title']) && is_string($scope['graph_title']) ? $scope['graph_title'] : null,
        );
    }

    public function authorize(GraphSubject $subject, GraphAccess $access): bool
    {
        return $subject instanceof LegacySubject && $subject->authorized;
    }

    public function describe(GraphSubject $subject): GraphDescription
    {
        $legacy = $this->legacySubject($subject);

        return new GraphDescription(
            title: $legacy->graphTitle ?? $legacy->params->getTitle(),
            subtitle: $legacy->subtitle,
            device: $legacy->device,
            port: $legacy->port,
        );
    }

    public function plan(GraphSubject $subject, GraphQuery $query): RenderPlan
    {
        $legacy = $this->legacySubject($subject);
        $template = self::templateFile($this->type, $this->subtype) ?? throw new LogicException('Template disappeared');

        ob_start();
        try {
            $scope = $legacy->runner->template($template);
        } finally {
            $output = (string) ob_get_clean();
        }

        $graph_params = $legacy->params;

        // jpgraph based graphs output the image directly
        if (($scope['graph'] ?? null) instanceof JpGraph) {
            return new PrebuiltImage(new GraphImage(ImageFormat::Png, $graph_params->getTitle(), $output));
        }

        if ($output !== '' && Debug::isEnabled()) {
            echo $output;
        }

        $rrd_options = $scope['rrd_options'] ?? [];
        if (empty($rrd_options) || ! is_array($rrd_options)) {
            throw new GraphRenderFailed('Graph Definition Error', 'Def Error');
        }

        return new RrdCommand([...$graph_params->toRrdOptions(), ...$rrd_options], $graph_params->getTitle());
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * Entities identified by the request itself
     *
     * @return array{0: ?Device, 1: ?Port}
     */
    private function requestEntities(GraphQuery $query): array
    {
        if ($query->deviceId) {
            return [DeviceCache::get($query->deviceId), null];
        }

        if (count($query->ids) === 1) {
            if ($this->type === 'port') {
                $port = PortCache::get($query->ids[0]);

                return [$port?->device, $port];
            }

            if ($this->type === 'device') {
                return [DeviceCache::get($query->ids[0]), null];
            }
        }

        return [null, null];
    }

    /**
     * Prefer the device from the request, otherwise use the one the auth file found
     */
    private function resolveDevice(?Device $requested, mixed $fromAuth): ?Device
    {
        if ($requested?->exists) {
            return $requested;
        }

        if ($fromAuth instanceof Device && $fromAuth->exists) {
            return $fromAuth;
        }

        if (is_array($fromAuth) && isset($fromAuth['device_id'])) {
            $device = DeviceCache::get($fromAuth['device_id']);

            return $device->exists ? $device : null;
        }

        return null;
    }

    private function legacySubject(GraphSubject $subject): LegacySubject
    {
        if (! $subject instanceof LegacySubject) {
            throw new LogicException('Legacy graphs require a legacy subject');
        }

        return $subject;
    }

    private static function templateFile(string $type, string $subtype): ?string
    {
        foreach ([$subtype, 'generic'] as $name) {
            $file = self::path($type, $name);
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    private static function path(string $type, string $name): string
    {
        return base_path(self::BASE_DIR . "/$type/$name.inc.php");
    }

    private static function validName(string $name): bool
    {
        return $name !== '' && $name === basename($name) && ! str_starts_with($name, '.');
    }
}
