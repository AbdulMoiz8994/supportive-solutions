<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = Employee::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('update', $employee);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:employees,email,'.$id],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'champs_username' => ['nullable', 'string', 'max:255'],
            'champs_association_date' => ['nullable', 'date'],
            'status_id' => ['nullable', 'exists:statuses,id'],
        ];
    }
}
