<?php

namespace App\View\Components\ecommerce\product;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Illuminate\Http\Request;

class AddProductForm extends Component
{
    public $productName;
    public $category;
    public $brand;
    public $color;
    public $weight;
    public $length;
    public $width;
    public $description;
    public $pricingWeight;
    public $pricingLength;
    public $pricingWidth;
    public $stockQuantity;
    public $availability;
    public $action;
    public $image;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        // Initialize with old input or empty values
        $this->productName = old('product_name', '');
        $this->category = old('category', '');
        $this->brand = old('brand', '');
        $this->color = old('color', '');
        $this->weight = old('weight', '');
        $this->length = old('length', '');
        $this->width = old('width', '');
        $this->description = old('description', '');
        $this->pricingWeight = old('pricing_weight', '');
        $this->pricingLength = old('pricing_length', '');
        $this->pricingWidth = old('pricing_width', '');
        $this->stockQuantity = old('stock_quantity', 1);
        $this->availability = old('availability', '');
        $this->action = old('action', '');
        $this->image = '';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.ecommerce.product.add-product-form');
    }
}
