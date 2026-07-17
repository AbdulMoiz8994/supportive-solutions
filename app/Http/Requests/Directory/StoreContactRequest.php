<?php

namespace App\Http\Requests\Directory;

use App\Http\Requests\Directory\Concerns\ValidatesContactFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    use ValidatesContactFields;

    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Contact::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->contactFieldRules();
    }
}
