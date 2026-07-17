<?php

namespace App\Http\Requests\BillingClaimsAudit;

use Illuminate\Foundation\Http\FormRequest;

class OverrideBillingBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('override', $this->route('billing_claims_audit'));
    }

    public function rules(): array
    {
        return [
            'override_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
