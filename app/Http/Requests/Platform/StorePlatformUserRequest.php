<?php

namespace App\Http\Requests\Platform;

use App\Models\User;
use App\Services\SuperAdminGuardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createPlatformUser', User::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in([
                User::ROLE_SUPER_ADMIN,
                User::ROLE_ADMIN,
                User::ROLE_STAFF,
                User::ROLE_EMPLOYEE,
            ])],
            'organization_id' => ['nullable', 'exists:organizations,id'],
        ];
    }
}
