<?php

namespace App\Graphing\Plans;

use App\Graphing\Contracts\RenderPlan;
use App\Graphing\GraphImage;

/**
 * An image that was already drawn, such as by the jpgraph based bill graphs
 */
final readonly class PrebuiltImage implements RenderPlan
{
    public function __construct(public GraphImage $image)
    {
    }
}
