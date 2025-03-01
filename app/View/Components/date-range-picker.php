<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;

class date-range-picker extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.date-range-picker');
    }
}
