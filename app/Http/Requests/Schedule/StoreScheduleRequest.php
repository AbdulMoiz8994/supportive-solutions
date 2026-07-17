<?php

namespace App\Http\Requests\Schedule;

use App\Http\Requests\Schedule\Concerns\ValidatesScheduleFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    use ValidatesScheduleFields;

    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Schedule::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->scheduleFieldRules();
    }
}
