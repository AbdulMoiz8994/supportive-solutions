<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class RunBillingCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('runCycle', \App\Models\Billing::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
