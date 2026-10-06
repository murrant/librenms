<?php

namespace App\Graphing\Plans;

use App\Graphing\Contracts\RenderPlan;

/**
 * Complete rrdtool graph options, as produced by legacy graph templates
 */
final readonly class RrdCommand implements RenderPlan
{
    /**
     * @param  array<int, mixed>  $options
     */
    public function __construct(
        public array $options,
        public string $title,
    ) {
    }
}
