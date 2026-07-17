<?php

namespace App\Http\Requests\Document;

use App\Policies\DocumentPolicy;
use App\Services\DocumentStorageService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('create', \App\Models\Document::class)) {
            return false;
        }

        try {
            $documentable = app(DocumentStorageService::class)->resolveDocumentable(
                $this->input('documentable_type'),
                (int) $this->input('documentable_id')
            );

            return app(DocumentPolicy::class)->attach($this->user(), $documentable);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\Illuminate\Validation\ValidationException) {
            return false;
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signature' => ['required', 'string', 'max:'.DocumentStorageService::MAX_SIGNATURE_BYTES],
            'documentable_id' => ['required', 'integer', 'min:1'],
            'documentable_type' => ['required', 'string', 'in:Client,Employee,Intake'],
            'document_name' => ['required', 'string', 'max:255'],
        ];
    }
}
