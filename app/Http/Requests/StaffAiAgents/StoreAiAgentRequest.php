<?php

namespace App\Http\Requests\StaffAiAgents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_ai_agents') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'template_slug' => ['nullable', 'string', 'max:80'],
            'role_description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:16'],
            'autonomy_mode' => ['required', Rule::in(array_keys(config('staff_ai_agents.autonomy_modes', [])))],
            'scope_programs' => ['nullable', 'array'],
            'scope_programs.*' => ['string', Rule::in(config('ai_agent_registry.programs', []))],
            'scope_location_ids' => ['nullable', 'array'],
            'scope_location_ids.*' => ['integer', 'exists:locations,id'],
            'scope_client_ids' => ['nullable', 'array'],
            'scope_client_ids.*' => ['integer', 'exists:clients,id'],
            'credential_keys' => ['nullable', 'array'],
            'credential_keys.*' => ['string', Rule::in(array_keys(\App\Models\IntegrationCredential::supportedKeys()))],
            'permission_slugs' => ['nullable', 'array'],
            'permission_slugs.*' => ['string', 'exists:permissions,slug'],
        ];
    }
}
