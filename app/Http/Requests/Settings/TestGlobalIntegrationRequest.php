<?php

namespace App\Http\Requests\Settings;

use App\Services\GlobalIntegrationTestService;
use Illuminate\Foundation\Http\FormRequest;

class TestGlobalIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! app(GlobalIntegrationTestService::class)->isTestable((string) $value)) {
                        $fail('This integration cannot be tested.');
                    }
                },
            ],
            'draft' => ['nullable', 'array'],
            'draft.username' => ['nullable', 'string', 'max:255'],
            'draft.password' => ['nullable', 'string', 'max:500'],
            'draft.api_key' => ['nullable', 'string', 'max:500'],
            'draft.metadata' => ['nullable', 'array'],
            'draft.metadata.*' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
