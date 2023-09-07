<?php

namespace App\View\Components;

use Illuminate\View\Component;

class OptionBar extends Component
{

    /**
     * @param string $name Name of the option bar
     * @param array $options Options to show. Format: ['Name' => ['text' => 'Display Text', 'link' => 'https://...']]
     * @param int|string|null $selected Name of selected option.  Key from options array
     */
    public function __construct(
        public string $name = '',
        public array $options = [],
        public int|string|null $selected = null,
    ) {}

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.option-bar');
    }
}
