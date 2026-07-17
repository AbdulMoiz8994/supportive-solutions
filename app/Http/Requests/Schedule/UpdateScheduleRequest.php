<?php

namespace App\Http\Requests\Schedule;

use App\Http\Requests\Schedule\Concerns\ValidatesScheduleFields;
use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    use ValidatesScheduleFields;

    public function authorize(): bool
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('update', $schedule);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->scheduleFieldRules(requireStatus: true);
    }
}
