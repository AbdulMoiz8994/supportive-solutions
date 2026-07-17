<?php

namespace App\Http\Requests\Platform;

use App\Models\User;
use App\Services\SuperAdminGuardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedUser = User::findOrFail($this->route('id'));

        return $this->user()->can('updatePlatformUser', $managedUser);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::in([
                User::ROLE_SUPER_ADMIN,
                User::ROLE_ADMIN,
                User::ROLE_STAFF,
                User::ROLE_EMPLOYEE,
            ])],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $managedUser = User::find($this->route('id'));
            $guard = app(SuperAdminGuardService::class);

            if ($managedUser && ! $guard->canDemoteSuperAdmin($managedUser, $this->input('role'))) {
                $validator->errors()->add('role', 'Cannot demote the last Super Administrator.');
            }
        });
    }
}
