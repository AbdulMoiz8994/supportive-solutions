<?php

namespace App\Http\Requests\Intake;

use App\Models\Intake;
use Illuminate\Foundation\Http\FormRequest;

class UploadIntakeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('uploadDocument', $intake);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxUpload = (int) config('uploads.max_kilobytes', 10240);

        return [
            'file' => ['required', 'file', 'max:'.$maxUpload],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
