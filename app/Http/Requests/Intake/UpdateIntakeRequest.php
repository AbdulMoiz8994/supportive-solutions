<?php

namespace App\Http\Requests\Intake;

use App\Models\Intake;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('update', $intake);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'source' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
