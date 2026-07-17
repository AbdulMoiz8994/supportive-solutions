<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use App\Services\Communication\CommunicationSendService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EfaxController extends Controller
{
    public function create()
    {
        return redirect()->route('communications.index', ['compose' => 'efax']);
    }

    public function store(Request $request, CommunicationSendService $sendService)
    {
        $this->authorize('send', Communication::class);

        $validated = $request->validate([
            'to' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if (! $request->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'attachment' => 'Attach a PDF to send via eFax.',
            ]);
        }

        try {
            $coverNote = trim((string) ($validated['subject'] ?? ''));
            if ($coverNote === '' && ! empty($validated['message'])) {
                $coverNote = trim((string) $validated['message']);
            }

            $communication = $sendService->sendEfax($request->user(), [
                'recipient_fax' => $validated['to'],
                'cover_note' => $coverNote,
            ], $request->file('attachment'));

            $message = $communication->status === Communication::STATUS_SENT
                ? 'Fax sent and logged in Communications.'
                : 'Fax logged; delivery reported a failure.';

            return redirect()
                ->route('communications.index')
                ->with('success', $message);
        } catch (ValidationException $e) {
            return redirect()
                ->route('communications.index', ['compose' => 'efax'])
                ->withErrors($e->errors())
                ->withInput();
        }
    }
}
