<?php

/**
 * GraphDefinition.php
 *
 * Describes what a graph shows. Renderers decide how to draw it.
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

namespace App\Graphing\Definition;

use App\Graphing\Contracts\RenderPlan;
use InvalidArgumentException;

final readonly class GraphDefinition implements RenderPlan
{
    /**
     * @param  list<Series>  $series
     * @param  string  $palette  graph_colours palette for series without a color
     * @param  bool  $legendTotals  show the total of each series in the legend (StackedArea layout)
     * @param  bool  $legendRawValues  legend shows values before the series multiplier is applied
     */
    public function __construct(
        public array $series,
        public Layout $layout = Layout::Lines,
        public Axis $axis = new Axis,
        public string $title = '',
        public string $palette = 'mixed',
        public bool $legendTotals = false,
        public string $totalUnits = '',
        public bool $legendRawValues = false,
    ) {
        $keys = array_map(fn (Series $series) => $series->key, $series);
        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Series keys must be unique');
        }
    }

    public function withTitle(string $title): self
    {
        return new self($this->series, $this->layout, $this->axis, $title, $this->palette, $this->legendTotals, $this->totalUnits, $this->legendRawValues);
    }

    /**
     * @param  list<string>  $keys
     */
    public function allOptional(array $keys): bool
    {
        foreach ($this->series as $series) {
            if (! $series->optional && in_array($series->key, $keys, true)) {
                return false;
            }
        }

        return true;
    }
}
