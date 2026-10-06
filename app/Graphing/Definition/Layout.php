<?php

namespace App\Graphing\Definition;

enum Layout
{
    /** One line per series, optionally with a shaded area below */
    case Lines;
    /** Series stacked as filled areas */
    case StackedArea;
}
