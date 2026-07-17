<?php

namespace App\Http\Requests\Location;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Location::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
