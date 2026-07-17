<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleReportRequest extends FormRequest
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
            'report_slug' => ['required', 'string', Rule::in(array_keys(config('reports.reports', [])))],
            'frequency' => ['required', 'string', Rule::in(['monthly', 'weekly', 'quarterly', 'per_run'])],
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx', 'pdf'])],
            'recipients' => ['required', 'string'],
            'period' => ['nullable', 'string'],
        ];
    }
}
