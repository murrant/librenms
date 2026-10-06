<?php

namespace App\Graphing\Rrd\Layouts;

use App\Graphing\Definition\GraphDefinition;
use App\Graphing\GraphParameters;
use App\Graphing\Rrd\CompiledSeries;

interface RrdLayout
{
    /**
     * @param  non-empty-list<CompiledSeries>  $series
     * @return list<string>
     */
    public function compile(GraphDefinition $definition, array $series, GraphParameters $params): array;
}
