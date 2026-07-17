<?php

namespace App\Http\Requests\Schedule;

use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;

class CancelScheduleRequest extends FormRequest
{
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
        return [];
    }
}
