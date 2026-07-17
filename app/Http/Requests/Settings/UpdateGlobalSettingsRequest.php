<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateGlobalSettingsRequest extends FormRequest
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
        return match ($this->input('_tab')) {
            'programs-rates' => $this->programsRules(),
            'billing-claims' => $this->billingClaimsRules(),
            'security-compliance' => $this->securityComplianceRules(),
            'access-activation' => $this->accessRules(),
            'ai-automation' => $this->automationRules(),
            'notifications-language' => $this->notificationsRules(),
            default => $this->legacyRules(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function legacyRules(): array
    {
        return array_merge(
            $this->securityComplianceRules(),
            [
                'billing' => ['required', 'array'],
                'billing.default_cycle' => ['required', 'string', 'in:monthly,weekly,biweekly'],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function billingClaimsRules(): array
    {
        return [
            'billing' => ['required', 'array'],
            'billing.default_asw_email' => ['nullable', 'email', 'max:255'],
            'billing.sigma_portal_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function programsRules(): array
    {
        return [
            'programs' => ['required', 'array'],
            'programs.mich_hourly_rate' => ['required', 'numeric', 'min:1', 'max:500'],
            'programs.dhs_hourly_rate' => ['required', 'numeric', 'min:1', 'max:500'],
            'programs.default_caregiver_wage' => ['required', 'numeric', 'min:1', 'max:500'],
            'programs.employment_type' => ['required', 'string', Rule::in(array_keys(config('global_settings.employment_types', [])))],
            'programs.pay_grace_days' => ['required', 'integer', 'min:1', 'max:30'],
            'programs.batch_build_day' => ['required', 'string', Rule::in(array_keys(config('global_settings.batch_build_days', [])))],
            'programs.pay_day' => ['required', 'string', Rule::in(array_keys(config('global_settings.pay_days', [])))],
            'programs.roll_late_forms' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function securityComplianceRules(): array
    {
        return [
            'security' => ['required', 'array'],
            'security.session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'security.require_2fa' => ['sometimes', 'boolean'],
            'security.phi_access_logging' => ['sometimes', 'boolean'],
            'security.ip_restrictions' => ['sometimes', 'boolean'],
            'uploads' => ['required', 'array'],
            'uploads.max_file_size_kb' => ['required', 'integer', 'min:512', 'max:51200'],
            'retention' => ['required', 'array'],
            'retention.document_retention_days' => ['required', 'integer', 'min:365', 'max:3650'],
            'flags' => ['sometimes', 'array'],
            'flags.maintenance_mode' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function accessRules(): array
    {
        return [
            'access' => ['required', 'array'],
            'access.signup_mode' => ['required', 'string', Rule::in(array_keys(config('global_settings.signup_modes', [])))],
            'access.code_expiry_days' => ['required', 'integer', Rule::in(array_keys(config('global_settings.code_expiry_options', [])))],
            'access.bind_code_to_caregiver' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function automationRules(): array
    {
        return [
            'automation' => ['required', 'array'],
            'automation.miss_rate_ceiling' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'automation.default_autonomy' => ['required', 'string', Rule::in(array_keys(config('global_settings.autonomy_modes', [])))],
            'automation.approval_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'automation.single_approver' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationsRules(): array
    {
        return [
            'notifications' => ['required', 'array'],
            'notifications.supported_languages' => ['required', 'array', 'min:1'],
            'notifications.supported_languages.*' => ['string', Rule::in(array_keys(config('global_settings.supported_languages', [])))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('security')) {
            $merge['security'] = array_merge($this->input('security', []), [
                'require_2fa' => $this->boolean('security.require_2fa'),
                'phi_access_logging' => $this->boolean('security.phi_access_logging'),
                'ip_restrictions' => $this->boolean('security.ip_restrictions'),
            ]);
        }

        if ($this->has('flags')) {
            $merge['flags'] = array_merge($this->input('flags', []), [
                'maintenance_mode' => $this->boolean('flags.maintenance_mode'),
            ]);
        }

        if ($this->has('programs')) {
            $merge['programs'] = array_merge($this->input('programs', []), [
                'roll_late_forms' => $this->boolean('programs.roll_late_forms'),
            ]);
        }

        if ($this->has('access')) {
            $merge['access'] = array_merge($this->input('access', []), [
                'bind_code_to_caregiver' => $this->boolean('access.bind_code_to_caregiver'),
            ]);
        }

        if ($this->has('automation')) {
            $merge['automation'] = array_merge($this->input('automation', []), [
                'single_approver' => $this->boolean('automation.single_approver'),
            ]);
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedFlat(): array
    {
        return Arr::dot($this->validated());
    }

    protected function failedValidation(Validator $validator): void
    {
        $tab = $this->input('_tab', 'agency-profile');

        throw (new ValidationException($validator))
            ->redirectTo(route('settings.global', ['tab' => $tab]));
    }
}
