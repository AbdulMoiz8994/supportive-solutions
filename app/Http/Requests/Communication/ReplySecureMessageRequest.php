<?php

namespace App\Http\Requests\Communication;

use App\Models\SecureMessageThread;
use Illuminate\Foundation\Http\FormRequest;

class ReplySecureMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SecureMessageThread $thread */
        $thread = $this->route('thread');

        return $this->user()->can('reply', $thread);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
