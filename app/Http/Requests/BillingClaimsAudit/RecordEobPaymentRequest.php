<?php

namespace App\Http\Requests\BillingClaimsAudit;

use Illuminate\Foundation\Http\FormRequest;

class RecordEobPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('billing_claims_audit'));
    }

    public function rules(): array
    {
        $maxKb = (int) config('billing_claims_audit.eob_max_upload_kb', 10240);
        $mimes = config('billing_claims_audit.eob_allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']);

        return [
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'payment_date' => ['nullable', 'date'],
            'adjustment_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'denial_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'denial_reason' => ['nullable', 'string', 'max:2000'],
            'adjustment_reason' => ['nullable', 'string', 'max:2000'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'eob_document' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', $mimes)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'paid_amount' => $this->input('paid_amount'),
        ], fn ($v) => $v !== null && $v !== ''));
    }
}
