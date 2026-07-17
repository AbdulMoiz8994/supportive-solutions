<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollWageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateWage', $this->route('payRecord'));
    }

    public function rules(): array
    {
        $min = config('payroll.wage.min_hourly', 7.25);
        $max = config('payroll.wage.max_hourly', 100.00);

        return [
            'hourly_wage' => ['required', 'numeric', "min:{$min}", "max:{$max}"],
        ];
    }
}
