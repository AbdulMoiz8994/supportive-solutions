<?php

namespace App\Http\Requests\Intake;

use App\Models\Intake;
use Illuminate\Foundation\Http\FormRequest;

class LogIntakeCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('logCall', $intake);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
