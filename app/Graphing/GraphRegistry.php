<?php

/**
 * GraphRegistry.php
 *
 * Maps graph names to handlers. Graphs not explicitly registered fall back to legacy templates.
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

namespace App\Graphing;

use App\Facades\LibrenmsConfig;
use App\Graphing\Contracts\Graph;
use App\Graphing\Contracts\GraphHandler;
use App\Graphing\Exceptions\UnknownGraph;
use App\Graphing\Legacy\LegacyGraphHandler;
use App\Graphing\Modern\ModernGraphHandler;
use App\Models\Device;
use InvalidArgumentException;

class GraphRegistry
{
    /** @var array<string, class-string<Graph|GraphHandler>> */
    private array $graphs = [];

    /**
     * @param  array<string, class-string<Graph|GraphHandler>>  $graphs  graph name (type_subtype) => graph or handler class
     */
    public function __construct(array $graphs = [])
    {
        foreach ($graphs as $name => $class) {
            $this->register($name, $class);
        }
    }

    /**
     * @param  class-string<Graph|GraphHandler>  $class
     */
    public function register(string $name, string $class): void
    {
        if (! preg_match('/^[a-z0-9]+_[a-zA-Z0-9_-]+$/', $name)) {
            throw new InvalidArgumentException("Invalid graph name: $name");
        }

        if (! is_subclass_of($class, Graph::class) && ! is_subclass_of($class, GraphHandler::class)) {
            throw new InvalidArgumentException("$class must implement " . Graph::class);
        }

        $this->graphs[$name] = $class;
    }

    /**
     * @throws UnknownGraph
     */
    public function handler(string $type, string $subtype): GraphHandler
    {
        $name = "{$type}_$subtype";

        if (isset($this->graphs[$name])) {
            $class = $this->graphs[$name];

            return is_subclass_of($class, Graph::class)
                ? new ModernGraphHandler(app($class))
                : app($class);
        }

        if (LegacyGraphHandler::exists($type, $subtype)) {
            return new LegacyGraphHandler($type, $subtype);
        }

        throw UnknownGraph::named($name);
    }

    /**
     * Graph specific validation rules, empty if the graph does not exist
     *
     * @return array<string, mixed>
     */
    public function rules(string $type, string $subtype): array
    {
        try {
            return $this->handler($type, $subtype)->rules();
        } catch (UnknownGraph) {
            return [];
        }
    }

    /**
     * @return string[]
     */
    public function types(): array
    {
        return ['device', 'port', 'application', 'munin', 'service'];
    }

    /**
     * Get all graph subtypes for the given type
     *
     * @return string[]
     */
    public function subtypes(string $type, ?Device $device = null): array
    {
        $types = LegacyGraphHandler::subtypes($type);

        $prefix = $type . '_';
        foreach (array_keys($this->graphs) as $name) {
            if (str_starts_with($name, $prefix)) {
                $types[] = substr($name, strlen($prefix));
            }
        }

        if ($device?->graphs) {
            $graphs = $device->graphs->pluck('graph');

            foreach (LibrenmsConfig::get('graph_types') as $type_data) {
                foreach (array_keys($type_data) as $subtype) {
                    if ($graphs->contains($subtype)) {
                        $types[] = $subtype;
                    }
                }
            }
        }

        $types = array_values(array_unique($types));
        sort($types);

        return $types;
    }
}
