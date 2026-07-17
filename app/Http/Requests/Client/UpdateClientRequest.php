<?php

namespace App\Http\Requests\Client;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = Client::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('update', $client);
    }

    protected function prepareForValidation(): void
    {
        // Services Requested carries a hidden empty marker so that unchecking every
        // box still submits the field (as "none") — drop the blanks before saving.
        if ($this->has('services_requested') && is_array($this->input('services_requested'))) {
            $this->merge([
                'services_requested' => array_values(array_filter(
                    $this->input('services_requested'),
                    fn ($v) => is_string($v) && $v !== ''
                )),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            // `sometimes` lets a single section save just its own fields without
            // tripping required rules on fields it didn't submit.
            'first_name'          => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'           => ['sometimes', 'required', 'string', 'max:255'],
            'dob'                 => ['nullable', 'date'],
            'email'               => ['nullable', 'email', 'unique:clients,email,'.$id],
            'phone'               => ['nullable', 'string', 'max:20'],
            'address'             => ['nullable', 'string', 'max:255'],
            'home_latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'home_longitude'      => ['nullable', 'numeric', 'between:-180,180'],
            'county'              => ['nullable', 'string', 'max:255'],
            'member_id'           => ['nullable', 'string', 'unique:clients,member_id,'.$id],
            'coverage_type_id'    => ['nullable', 'exists:coverage_types,id'],
            'status_id'           => ['nullable', 'exists:statuses,id'],
            'billing_rate'        => ['nullable', 'numeric', 'min:0'],

            // Personal / demographics dropdowns (previously missing — caused F1 revert bug)
            'gender'              => ['nullable', 'string', 'max:50'],
            'preferred_language'  => ['nullable', 'string', 'max:50'],
            'requires_translator' => ['nullable', 'string', 'max:10'],

            // Eligibility & Insurance
            'mco_name'            => ['nullable', 'string', 'max:100'],
            'medicare_id'         => ['nullable', 'string', 'max:30'],
            'health_plan_id'      => ['nullable', 'string', 'max:50'],

            // Directory-driven pickers (persisted to the contacts pivot)
            'coordinator_contact_id' => ['nullable', 'exists:contacts,id'],
            'asw_contact_id'         => ['nullable', 'exists:contacts,id'],

            // PCP & medical
            'pcp_contact_id'      => ['nullable', 'exists:contacts,id'],
            'pcp_phone'           => ['nullable', 'string', 'max:20'],
            'pcp_fax'             => ['nullable', 'string', 'max:20'],
            'pcp_npi'             => ['nullable', 'string', 'max:10'],
            'medical_conditions'  => ['nullable', 'string'],

            // Emergency contact (saved to contacts relation in controller)
            'emergency_name'         => ['nullable', 'string', 'max:255'],
            'emergency_relationship' => ['nullable', 'string', 'max:50'],
            'emergency_phone'        => ['nullable', 'string', 'max:20'],
            'emergency_email'        => ['nullable', 'email'],

            // Household / Live-In
            'lives_with_caregiver'           => ['nullable'],
            'live_in_exemption_status'       => ['nullable', 'string', 'max:30'],
            'live_in_exemption_submitted_at' => ['nullable', 'date'],
            'live_in_exemption_approved_at'  => ['nullable', 'date'],
            'live_in_exemption_expires_at'   => ['nullable', 'date'],
            'evv_status'                     => ['nullable', 'string', 'max:100'],

            // Intake & Screening tab
            'referral_source'           => ['nullable', 'string', 'max:100'],
            'referral_received_date'    => ['nullable', 'date'],
            'referred_by'               => ['nullable', 'string', 'max:255'],
            'currently_receiving_care'  => ['nullable', 'string', 'max:10'],
            'intake_taken_by'           => ['nullable', 'string', 'max:255'],
            'intake_date'               => ['nullable', 'date'],
            'eligibility_verified_date' => ['nullable', 'date'],
            'eligibility_result'        => ['nullable', 'string', 'max:30'],
            'services_requested'        => ['nullable', 'array'],
            'services_requested.*'      => ['string', 'max:60'],
            'initial_notes'             => ['nullable', 'string', 'max:5000'],
        ];
    }
}
