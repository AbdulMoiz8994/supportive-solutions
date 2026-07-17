<?php

namespace App\Http\Requests\StaffAiAgents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('edit_staff') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'role_description' => ['nullable', 'string', 'max:500'],
            'autonomy_mode' => ['required', Rule::in(array_keys(config('staff_ai_agents.autonomy_modes', [])))],
            'hold_on_gate_fail' => ['nullable', 'boolean'],
            'auto_resubmit' => ['nullable', 'boolean'],
            'approval_threshold' => ['nullable', 'integer', 'min:0'],
            'action_autonomy' => ['nullable', 'array'],
            'action_autonomy.*.key' => ['required_with:action_autonomy', 'string'],
            'action_autonomy.*.mode' => ['required_with:action_autonomy', Rule::in(array_keys(config('ai_agent_registry.action_modes', [])))],
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'hold_on_gate_fail' => $this->boolean('hold_on_gate_fail'),
            'auto_resubmit' => $this->boolean('auto_resubmit'),
        ]);

        // Unchecked checkboxes / empty multi-selects are omitted from POST — treat as empty arrays.
        foreach (['scope_programs', 'scope_location_ids', 'scope_client_ids', 'credential_keys', 'permission_slugs'] as $field) {
            if (! $this->has($field)) {
                $this->merge([$field => []]);
            }
        }
    }
}
