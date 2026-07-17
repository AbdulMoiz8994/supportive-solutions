<?php

namespace App\Http\Requests\Directory;

use App\Http\Requests\Directory\Concerns\ValidatesContactFields;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    use ValidatesContactFields;

    public function authorize(): bool
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('update', $contact);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->contactFieldRules();
    }
}
