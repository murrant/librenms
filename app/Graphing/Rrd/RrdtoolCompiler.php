<?php

/**
 * RrdtoolCompiler.php
 *
 * Turns a GraphDefinition into rrdtool graph options.
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

namespace App\Graphing\Rrd;

use App\Graphing\Definition\GraphDefinition;
use App\Graphing\Definition\Layout;
use App\Graphing\GraphQuery;
use App\Graphing\Rrd\Layouts\LinesLayout;
use App\Graphing\Rrd\Layouts\RrdLayout;
use App\Graphing\Rrd\Layouts\StackedAreaLayout;
use App\TimeSeries\Contracts\RrdPathResolver;

class RrdtoolCompiler
{
    public function __construct(private readonly RrdPathResolver $resolver)
    {
    }

    /**
     * Always compiles from the full definition, so derived values (stacks, totals) never reference skipped series.
     *
     * @param  list<string>  $skip  keys of series to leave out
     */
    public function compile(GraphDefinition $definition, GraphQuery $query, array $skip = []): CompiledGraph
    {
        $params = $query->parameters();
        $params->title = $definition->title;
        if ($definition->axis->min !== null) {
            $params->scale_min = (int) $definition->axis->min;
        }
        if ($definition->axis->max !== null) {
            $params->scale_max = (int) $definition->axis->max;
        }

        $files = [];
        $series = [];
        foreach ($definition->series as $index => $item) {
            if (in_array($item->key, $skip, true)) {
                continue;
            }

            $path = $this->resolver->resolve($item->metric);
            $relativePath = $path->relativePath();
            $files[$relativePath]['path'] = $path;
            $files[$relativePath]['keys'][] = $item->key;

            $color = $item->color ?? Palette::color($definition->palette, $index); // by definition index so colors are stable
            $series[] = new CompiledSeries($item, (string) $path, $color, 's' . count($series));
        }

        if ($series === []) {
            return new CompiledGraph([], [], $params->getTitle());
        }

        $options = $this->layout($definition->layout)->compile($definition, $series, $params);

        if ($skip !== [] && $params->visible('legend')) {
            $options[] = 'COMMENT:' . count($skip) . ' series without data not shown\l';
        }

        // rrdtool base options last, the layout may adjust parameters
        return new CompiledGraph([...$params->toRrdOptions(), ...$options], $files, $params->getTitle());
    }

    private function layout(Layout $layout): RrdLayout
    {
        return match ($layout) {
            Layout::Lines => new LinesLayout,
            Layout::StackedArea => new StackedAreaLayout,
        };
    }
}
