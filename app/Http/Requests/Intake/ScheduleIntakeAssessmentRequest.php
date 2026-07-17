<?php

namespace App\Http\Requests\Intake;

use App\Models\Intake;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleIntakeAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('scheduleAssessment', $intake);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessment_date' => ['nullable', 'date'],
        ];
    }
}
