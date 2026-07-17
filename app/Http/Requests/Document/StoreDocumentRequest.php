<?php

namespace App\Http\Requests\Document;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Intake;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Document::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxUpload = (int) config('uploads.max_kilobytes', 10240);

        return [
            'documentable_id' => ['required', 'integer', 'min:1'],
            'documentable_type' => ['required', 'string', 'in:Client,Employee,Intake'],
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:'.$maxUpload, 'mimes:pdf,doc,docx,jpg,jpeg,png,gif'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = (string) $this->input('documentable_type');
            $id = (int) $this->input('documentable_id');

            if ($type === '' || $id < 1) {
                return;
            }

            $modelMap = [
                'Client' => Client::class,
                'Employee' => Employee::class,
                'Intake' => Intake::class,
            ];

            $modelClass = $modelMap[$type] ?? null;
            if (! $modelClass) {
                return;
            }

            $exists = $modelClass::withoutGlobalScopes()
                ->whereKey($id)
                ->exists();

            if (! $exists) {
                $validator->errors()->add('documentable_id', 'The selected subject does not exist.');
            }
        });
    }
}
