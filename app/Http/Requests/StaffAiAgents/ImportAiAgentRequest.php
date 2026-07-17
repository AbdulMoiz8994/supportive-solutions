<?php

namespace App\Http\Requests\StaffAiAgents;

use Illuminate\Foundation\Http\FormRequest;

class ImportAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_ai_agents') ?? false;
    }

    public function rules(): array
    {
        return [
            'import_file' => ['required', 'file', 'mimes:json,txt', 'max:5120'],
        ];
    }
}
