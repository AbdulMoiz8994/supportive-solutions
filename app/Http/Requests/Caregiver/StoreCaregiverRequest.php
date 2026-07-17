<?php

namespace App\Http\Requests\Caregiver;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaregiverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createCaregiver', \App\Models\Employee::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:employees,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'preferred_language' => ['nullable', 'string', 'max:100'],
            'id_expiry_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'champs_username' => ['nullable', 'string', 'max:100'],
            'champs_association_date' => ['nullable', 'date'],
            'is_18_plus' => ['nullable'],
            'is_work_eligible' => ['nullable'],
            'has_background_check' => ['nullable'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
