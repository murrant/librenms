<?php

namespace App\Graphing\Definition;

final readonly class Axis
{
    /**
     * @param  string  $label  describes the values, shown as the legend header
     * @param  string  $units  appended to values in the legend
     */
    public function __construct(
        public string $label = '',
        public string $units = '',
        public ?float $min = null,
        public ?float $max = null,
    ) {
    }
}
