<?php

namespace App\Graphing\Rrd\Layouts;

use App\Graphing\Definition\GraphDefinition;
use App\Graphing\GraphParameters;
use LibreNMS\Data\Store\Rrd;

/**
 * Equivalent of the legacy generic_multi_simplex_seperated.inc.php
 */
class StackedAreaLayout implements RrdLayout
{
    public function compile(GraphDefinition $definition, array $series, GraphParameters $params): array
    {
        $precision = $params->float_precision;
        $previous = $params->visible('previous');
        // without totals there is room for longer labels
        $headerLength = $definition->legendTotals ? 12 : 14;
        $labelLength = $definition->legendTotals ? 12 : 16;

        $options = ['COMMENT:' . Rrd::fixedSafeDescr($definition->axis->label, $headerLength) . "        Now      Min     Max     Avg\l"];
        $previousIds = [];

        foreach ($series as $position => $compiled) {
            $raw = $compiled->id;
            $options[] = $compiled->def('', 'AVERAGE');
            $options[] = $compiled->def('min', 'MIN');
            $options[] = $compiled->def('max', 'MAX');

            $multiplier = $compiled->series->multiplier;
            $id = $raw;
            if ($multiplier != 1.0) {
                foreach (['', 'min', 'max'] as $suffix) {
                    $options[] = "CDEF:{$raw}m$suffix=$raw$suffix,$multiplier,*";
                }
                $id = $raw . 'm';
            }

            if ($previous) {
                $options[] = "DEF:{$raw}p=$compiled->file:{$compiled->series->field}:AVERAGE:start=$params->prev_from:end=$params->from";
                $options[] = "SHIFT:{$raw}p:$params->period";
                $options[] = "CDEF:{$raw}pz={$raw}p,UN,0,{$raw}p,IF" . ($multiplier != 1.0 ? ",$multiplier,*" : '');
                $previousIds[] = $raw . 'pz';
            }

            if ($definition->legendTotals) {
                $options[] = "VDEF:{$raw}tot=$raw,TOTAL";
            }

            $descr = Rrd::fixedSafeDescr($compiled->series->label, $labelLength);
            $options[] = "AREA:$id#$compiled->color:$descr" . ($position > 0 ? ':STACK' : '');

            $legend = $definition->legendRawValues ? $raw : $id;
            $options[] = "GPRINT:$legend:LAST:%5.{$precision}lf%s";
            $options[] = "GPRINT:{$legend}min:MIN:%5.{$precision}lf%s";
            $options[] = "GPRINT:{$legend}max:MAX:%5.{$precision}lf%s";
            $options[] = "GPRINT:$legend:AVERAGE:%5.{$precision}lf%s\\n";

            if ($definition->legendTotals) {
                $options[] = "GPRINT:{$raw}tot:%6.{$precision}lf%s" . Rrd::safeDescr($definition->totalUnits);
            }

            $options[] = 'COMMENT:\n';
        }

        if ($previousIds !== []) {
            $options[] = 'CDEF:previous=' . implode(',', $previousIds) . str_repeat(',+', count($previousIds) - 1);
            $options[] = 'AREA:previous#99999999:';
            $options[] = 'LINE1.25:previous#666666:';
        }

        return $options;
    }
}
