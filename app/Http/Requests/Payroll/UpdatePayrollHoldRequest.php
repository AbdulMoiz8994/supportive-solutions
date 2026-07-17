<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('applyHold', $this->route('payRecord'));
    }

    public function rules(): array
    {
        return [
            'hold_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('hold_reason')) {
            $this->merge([
                'hold_reason' => strip_tags((string) $this->input('hold_reason')),
            ]);
        }
    }
}
