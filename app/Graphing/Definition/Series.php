<?php

namespace App\Graphing\Definition;

use App\TimeSeries\MetricIdentity;

/**
 * One plotted value: a field of a metric plus how to present it
 */
final readonly class Series
{
    /**
     * @param  string  $key  unique within the graph
     * @param  bool  $optional  when the metric has no data the graph is drawn without this series instead of failing
     * @param  string|null  $color  hex color, defaults to the next palette color
     * @param  bool  $area  shade the area under a line (Lines layout)
     * @param  bool  $invert  draw below the axis (Lines layout)
     * @param  float  $multiplier  applied to values before drawing
     */
    public function __construct(
        public string $key,
        public MetricIdentity $metric,
        public string $field,
        public string $label,
        public bool $optional = false,
        public ?string $color = null,
        public bool $area = false,
        public bool $invert = false,
        public float $multiplier = 1.0,
    ) {
    }
}
