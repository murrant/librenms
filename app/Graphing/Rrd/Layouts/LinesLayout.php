<?php

namespace App\Graphing\Rrd\Layouts;

use App\Facades\LibrenmsConfig;
use App\Graphing\Definition\GraphDefinition;
use App\Graphing\GraphParameters;
use LibreNMS\Data\Store\Rrd;

/**
 * Equivalent of the legacy generic_multi_line.inc.php
 */
class LinesLayout implements RrdLayout
{
    public function compile(GraphDefinition $definition, array $series, GraphParameters $params): array
    {
        $precision = $params->float_precision;
        $units = $definition->axis->units;
        $descrLength = $definition->legendTotals ? 12 : 14;
        $invert = LibrenmsConfig::get('webui.graph_stacked') ? '1' : '-1';

        $defs = ['COMMENT:' . Rrd::fixedSafeDescr($definition->axis->label, $descrLength) . "      Now      Min      Max     Avg\l"];
        $draw = [];

        foreach ($series as $compiled) {
            $id = $compiled->id;
            $defs[] = $compiled->def('', 'AVERAGE');
            $defs[] = $compiled->def('min', 'MIN');
            $defs[] = $compiled->def('max', 'MAX');

            if ($compiled->series->multiplier != 1.0) {
                foreach (['', 'min', 'max'] as $suffix) {
                    $defs[] = "CDEF:{$id}m$suffix=$id$suffix," . $compiled->series->multiplier . ',*';
                }
                $id .= 'm';
            }

            $line = $id;
            if ($compiled->series->invert) {
                $defs[] = "CDEF:{$id}i=$id,$invert,*";
                $line = $id . 'i';
            }

            $descr = Rrd::fixedSafeDescr($compiled->series->label, $descrLength);
            $draw[] = "LINE1.25:$line#$compiled->color:$descr";
            if ($compiled->series->area) {
                $draw[] = "AREA:$line#{$compiled->color}20";
            }

            $draw[] = "GPRINT:$id:LAST:%5.{$precision}lf%s$units";
            $draw[] = "GPRINT:{$id}min:MIN:%5.{$precision}lf%s$units";
            $draw[] = "GPRINT:{$id}max:MAX:%5.{$precision}lf%s$units";
            $draw[] = "GPRINT:$id:AVERAGE:%5.{$precision}lf%s$units\\n";
        }

        return [...$defs, ...$draw, 'HRULE:0#555555'];
    }
}
