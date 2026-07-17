<?php

namespace App\Http\Requests\ApiKey;

use App\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $apiKey = ApiKey::find($this->route('id'));

        return $apiKey && ($this->user()?->can('update', $apiKey) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
