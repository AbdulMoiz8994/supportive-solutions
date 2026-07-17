<?php

namespace App\Http\Requests\BillingClaimsAudit;

use App\Models\BillingClaimAudit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingClaimAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        $audit = $this->route('billing_claims_audit');

        return $this->user()->can('update', $audit);
    }

    public function rules(): array
    {
        return [
            'audit_status' => ['sometimes', 'required', Rule::in(BillingClaimAudit::auditStatuses())],
            'notes' => ['nullable', 'string', 'max:5000'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
