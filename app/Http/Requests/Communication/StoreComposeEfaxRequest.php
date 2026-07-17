<?php

namespace App\Http\Requests\Communication;

use App\Models\Communication;
use Illuminate\Foundation\Http\FormRequest;

class StoreComposeEfaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send', Communication::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_fax' => ['nullable', 'string', 'max:50'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'cover_note' => ['nullable', 'string', 'max:255'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasUpload = $this->hasFile('attachment');
            $hasDocument = $this->filled('document_id');

            if (! $hasUpload && ! $hasDocument) {
                $validator->errors()->add('attachment', 'Attach a PDF or choose a document from the client record.');
            }

            if (! $this->filled('recipient_fax') && ! $this->filled('contact_id')) {
                $validator->errors()->add('recipient_fax', 'Pick a directory contact or enter a fax number.');
            }
        });
    }
}
