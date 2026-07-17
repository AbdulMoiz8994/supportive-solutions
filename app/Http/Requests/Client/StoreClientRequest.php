<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Client::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:255'],
            'last_name'       => ['required', 'string', 'max:255'],
            'dob'             => ['nullable', 'date'],
            'ssn'             => ['nullable', 'string', 'max:11'],
            'email'           => ['nullable', 'email', 'unique:clients,email'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'address'         => ['nullable', 'string', 'max:255'],
            'county'          => ['nullable', 'string', 'max:255'],
            'member_id'       => ['nullable', 'string', 'regex:/^MD-\d{5}$/i', 'unique:clients,member_id'],
            'coverage_type_id' => ['required', 'exists:coverage_types,id'],
            'status_id'       => ['nullable', 'exists:statuses,id'],
            'billing_rate'    => ['nullable', 'numeric', 'min:0'],
            'gender'          => ['nullable', 'string', 'max:50'],
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'requires_translator' => ['nullable', 'string', 'max:10'],
            'medicare_id'     => ['nullable', 'string', 'max:20'],
            'health_plan_id'  => ['nullable', 'string', 'max:50'],
            'mco_name'        => ['nullable', 'string', 'max:100'],
            'pcp_name'        => ['nullable', 'string', 'max:255'],
            'pcp_phone'       => ['nullable', 'string', 'max:20'],
            'pcp_fax'         => ['nullable', 'string', 'max:20'],
            'pcp_npi'         => ['nullable', 'string', 'max:10'],
            'medical_conditions' => ['nullable', 'string'],
            'emergency_name'  => ['nullable', 'string', 'max:255'],
            'emergency_relationship' => ['nullable', 'string', 'max:50'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
            'emergency_email' => ['nullable', 'email'],
        ];
    }
}
