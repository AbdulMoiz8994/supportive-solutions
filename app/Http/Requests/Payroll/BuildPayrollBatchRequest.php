<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class BuildPayrollBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('buildBatch', \App\Models\PayRecord::class);
    }

    public function rules(): array
    {
        return [
            'period'     => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'record_ids' => ['nullable', 'array'],
            'record_ids.*' => ['integer'],
        ];
    }
}
