<?php

namespace App\View\Components\ecommerce\invoice;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class InvoiceDetailsTable extends Component
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
        return view('components.ecommerce.invoice.invoice-details-table');
    }
}
