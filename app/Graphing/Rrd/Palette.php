<?php

namespace App\Graphing\Rrd;

use App\Facades\LibrenmsConfig;

final class Palette
{
    /**
     * Color for the series at $index, stable regardless of which other series are drawn
     */
    public static function color(string $palette, int $index): string
    {
        $colors = LibrenmsConfig::get("graph_colours.$palette");
        if (! is_array($colors) || $colors === []) {
            $colors = LibrenmsConfig::get('graph_colours.mixed');
        }

        if (! is_array($colors) || $colors === []) {
            return '000000';
        }

        $colors = array_values($colors);

        return (string) $colors[$index % count($colors)];
    }
}
