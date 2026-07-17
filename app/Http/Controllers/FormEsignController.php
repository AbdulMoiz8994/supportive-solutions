<?php

namespace App\Http\Controllers;

use App\Services\FormEsignService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormEsignController extends Controller
{
    public function __construct(
        protected FormEsignService $esign,
    ) {}

    public function show(string $token): View
    {
        $submission = $this->esign->findByToken($token);

        abort_unless($submission, 404);

        $fields = $submission->fields_snapshot ?: ($submission->template?->fields ?? []);

        return view('pages.forms.esign', [
            'submission' => $submission,
            'templateName' => $submission->template?->name ?? 'Form',
            'fields' => $fields,
            'values' => $submission->field_values ?? [],
            'token' => $token,
            'signerName' => $submission->subjectName(),
        ]);
    }

    public function sign(Request $request, string $token)
    {
        $submission = $this->esign->findByToken($token);
        abort_unless($submission, 404);

        $validated = $request->validate([
            'signed_by_name' => 'required|string|max:120',
            'signature_image' => 'nullable|string',
        ]);

        try {
            $this->esign->completeRemoteSign(
                $submission,
                $validated['signed_by_name'],
                $validated['signature_image'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['signed_by_name' => $e->getMessage()]);
        }

        return view('pages.forms.esign-complete', [
            'formName' => $submission->template?->name ?? 'Form',
        ]);
    }
}
