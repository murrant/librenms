<?php

/**
 * GraphFactory.php
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

use Illuminate\Support\Str;
use LibreNMS\Exceptions\InvalidGraph;
use LibreNMS\Interfaces\Data\Graphing\GraphInterface;

class GraphFactory
{
    /**
     * @throws InvalidGraph
     */
    public function graphFor(string $name, array $vars = []): GraphInterface
    {
        if (! preg_match('/([a-z]+)_([a-zA-Z0-9_]+)/', $name, $matches)) {
            throw new InvalidGraph;
        }

        $type = $matches[1];
        $subtype = $matches[2];

        $vars['type'] ??= $name;

        // Look for a modern class, e.g. LibreNMS\Data\Graphing\Device\ProcessorSeparateGraph
        $className = 'LibreNMS\\Graphs\\' . ucfirst($type) . '\\' . Str::studly($subtype) . 'Graph';
        if (class_exists($className)) {
            return app($className, ['vars' => $vars]);
        }

        return new LegacyGraph($type, $subtype, $vars);
    }
}
