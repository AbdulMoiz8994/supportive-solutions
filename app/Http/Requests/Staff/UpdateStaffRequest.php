<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staffUser = User::findOrFail($this->route('id'));

        return $this->user()->can('update', $staffUser);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$id],
            'phone' => ['nullable', 'string'],
            'role' => ['required', 'exists:roles,name'],
            'location_ids' => ['required', 'array'],
            'location_ids.*' => ['exists:locations,id'],
        ];
    }
}
