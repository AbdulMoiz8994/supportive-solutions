<?php

namespace App\Http\Requests\Communication;

use App\Models\SecureMessageThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecureMessageThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SecureMessageThread::class);
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', Rule::exists('users', 'id')],
            'related_type' => ['nullable', Rule::in(['Client', 'Employee'])],
            'related_id' => ['nullable', 'integer'],
        ];
    }
}
