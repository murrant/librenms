<?php

if (\App\Facades\LibrenmsConfig::get('old_graphs')) {
    include 'includes/html/graphs/old_generic_simplex.inc.php';
} else {
    // Draw generic bits graph
    // args: ds_in, ds_out, rrd_filename, bg, legend, from, to, width, height, inverse, percentile
    include 'includes/html/graphs/common.inc.php';

    $unit_text = str_pad(substr((string) $unit_text, 0, 18), 18);
    $line_text = str_pad(substr((string) $line_text, 0, 12), 12);

    if ($multiplier) {
        $rrd_options[] = 'DEF:' . $name . '_o=' . $rrd_filename . ':' . $name . ':AVERAGE';
        $rrd_options[] = 'DEF:' . $name . '_max_o=' . $rrd_filename . ':' . $name . ':MAX';
        $rrd_options[] = 'DEF:' . $name . '_min_o=' . $rrd_filename . ':' . $name . ':MIN';

        if (empty($multiplier_action)) {
            $multiplier_action = '*';
        }

        $rrd_options[] = 'CDEF:' . $name . '=' . $name . "_o,$multiplier,$multiplier_action";
        $rrd_options[] = 'CDEF:' . $name . '_max=' . $name . "_max_o,$multiplier,$multiplier_action";
        $rrd_options[] = 'CDEF:' . $name . '_min=' . $name . "_min_o,$multiplier,$multiplier_action";
    } else {
        $rrd_options[] = 'DEF:' . $name . '=' . $rrd_filename . ':' . $name . ':AVERAGE';
        $rrd_options[] = 'DEF:' . $name . '_max=' . $rrd_filename . ':' . $name . ':MAX';
        $rrd_options[] = 'DEF:' . $name . '_min=' . $rrd_filename . ':' . $name . ':MIN';
    }

    if ($print_total) {
        $rrd_options[] = 'VDEF:' . $name . '_total=ds,TOTAL';
    }

    if ($percentile) {
        $rrd_options[] = 'VDEF:' . $name . '_percentile=' . $name . ',' . $percentile . ',PERCENT';
    }

    if ($graph_params->visible('previous')) {
        if ($multiplier) {
            $rrd_options[] = 'DEF:' . $name . '_oX=' . $rrd_filename . ':' . $name . ':AVERAGE:start=' . $prev_from . ':end=' . $from;
            $rrd_options[] = 'DEF:' . $name . '_max_oX=' . $rrd_filename . ':' . $name . ':MAX:start=' . $prev_from . ':end=' . $from;
            $rrd_options[] = 'SHIFT:' . $name . "_oX:$period";
            $rrd_options[] = 'SHIFT:' . $name . "_max_oX:$period";
            if (empty($multiplier_action)) {
                $multiplier_action = '*';
            }

            $rrd_options[] = 'CDEF:' . $name . 'X=' . $name . "_oX,$multiplier,$multiplier_action";
            $rrd_options[] = 'CDEF:' . $name . '_maxX=' . $name . "_max_oX,$multiplier,$multiplier_action";
        } else {
            $rrd_options[] = 'DEF:' . $name . 'X=' . $rrd_filename . ':' . $name . ':AVERAGE:start=' . $prev_from . ':end=' . $from;
            $rrd_options[] = 'DEF:' . $name . '_maxX=' . $rrd_filename . ':' . $name . ':MAX:start=' . $prev_from . ':end=' . $from;
            $rrd_options[] = 'SHIFT:' . $name . "X:$period";
            $rrd_options[] = 'SHIFT:' . $name . "_maxX:$period";
        }

        if ($print_total) {
            $rrd_options[] = 'VDEF:' . $name . '_totalX=ds,TOTAL';
        }

        if ($percentile) {
            $rrd_options[] = 'VDEF:' . $name . '_percentileX=' . $name . ',' . $percentile . ',PERCENT';
        }

        // if ($graph_max)
        // {
        // $rrd_options[] = "AREA:".$ds."_max#".$colour_area_max.":";
        // }
    }//end if

    if ($colour_minmax) {
        $rrd_options[] = 'AREA:' . $name . '_max#c5c5c5';
        $rrd_options[] = 'AREA:' . $name . '_min#ffffffff';
    } else {
        $rrd_options[] = 'AREA:' . $name . '#' . $colour_area . ':';
        if ($graph_max) {
            $rrd_options[] = 'AREA:' . $name . '_max#' . $colour_area_max . ':';
        }
    }

    if ($percentile) {
        $rrd_options[] = 'COMMENT:' . $unit_text . 'Now       Ave      Max     ' . $percentile . 'th %\\n';
    } else {
        $rrd_options[] = 'COMMENT:' . $unit_text . 'Now       Ave      Max\\n';
    }

    $rrd_options[] = 'LINE1.25:' . $name . '#' . $colour_line . ':' . $line_text;
    $rrd_options[] = 'GPRINT:' . $name . ':LAST:%6.' . $float_precision . 'lf%s';
    $rrd_options[] = 'GPRINT:' . $name . ':AVERAGE:%6.' . $float_precision . 'lf%s';
    $rrd_options[] = 'GPRINT:' . $name . '_max:MAX:%6.' . $float_precision . 'lf%s';

    if ($percentile) {
        $rrd_options[] = 'GPRINT:' . $name . '_percentile:%6.' . $float_precision . 'lf%s';
    }

    $rrd_options[] = 'COMMENT:\\n\\n';

    if ($print_total) {
        $rrd_options[] = 'GPRINT:' . $name . '_tot:Total\ %6.' . $float_precision . 'lf%s\)\l';
    }

    if ($percentile) {
        $rrd_options[] = 'LINE1:' . $name . '_percentile#aa0000';
    }

    if ($graph_params->visible('previous')) {
        $rrd_options[] = 'LINE1.25:' . $name . 'X#666666:Prev\\n';
        $rrd_options[] = 'AREA:' . $name . 'X#99999966:';
    }
}//end if
