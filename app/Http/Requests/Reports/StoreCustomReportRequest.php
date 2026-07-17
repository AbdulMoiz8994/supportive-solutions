<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'source' => ['required', 'string', 'in:clients,caregivers,billing,compliance'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:60'],
            'filters' => ['nullable', 'array'],
            'group_by' => ['nullable', 'string', 'max:60'],
            'schedule_frequency' => ['nullable', 'string', 'in:monthly,weekly,quarterly'],
            'schedule_recipients' => ['nullable', 'array'],
            'prompt' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
