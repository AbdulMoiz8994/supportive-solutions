<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class WebLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the "remember me" checkbox to a real boolean before validation.
     * An HTML checkbox sends "on" (or nothing / "") which the boolean rule rejects.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'remember' => $this->boolean('remember'),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }
}
