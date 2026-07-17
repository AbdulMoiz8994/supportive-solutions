<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class ReleasePayrollHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('releaseHold', $this->route('payRecord'));
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
