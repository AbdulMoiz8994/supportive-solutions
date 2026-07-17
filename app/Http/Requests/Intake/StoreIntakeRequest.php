<?php

namespace App\Http\Requests\Intake;

use Illuminate\Foundation\Http\FormRequest;

class StoreIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Intake::class);
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

            // Scan-first wizard fields (D1).
            'dob' => ['nullable', 'date', 'before:today'],
            'member_id' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'mco_name' => ['nullable', 'string', 'max:100'],
            'scan_data' => ['nullable', 'json'],
            'scanned_documents' => ['nullable', 'json'],
            'eligibility_status' => ['nullable', 'in:eligible,needs_verification,ineligible'],
            'eligibility_note' => ['nullable', 'string', 'max:500'],
            'eligibility_checked_at' => ['nullable', 'date'],
            'recommended_program' => ['nullable', 'string', 'max:50'],
            'program_track' => ['nullable', 'in:dhs,mich,ico,daaa'],
            'hours_per_week' => ['nullable', 'numeric', 'min:1', 'max:168'],
            'pa_units' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'coverage_type_id' => ['nullable', 'exists:coverage_types,id'],
        ];
    }
}
