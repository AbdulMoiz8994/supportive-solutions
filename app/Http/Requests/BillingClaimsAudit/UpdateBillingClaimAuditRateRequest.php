<?php

namespace App\Http\Requests\BillingClaimsAudit;

use App\Models\BillingClaimAudit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingClaimAuditRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $audit = $this->route('billing_claims_audit');

        return $this->user()->can('update', $audit);
    }

    public function rules(): array
    {
        return [
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }
}
