<?php

namespace App\View\Components\ui\list;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ListWithIcon extends Component
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
        return view('components.ui.list.list-with-icon');
    }
}
