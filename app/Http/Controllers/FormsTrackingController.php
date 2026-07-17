<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Services\FormsTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormsTrackingController extends Controller
{
    public function __construct(
        protected FormsTrackingService $forms,
    ) {}

    public function index(Request $request)
    {
        return view('pages.forms.index', $this->forms->pageData($this->organizationId(), $request, $request->user()));
    }

    public function createTemplate()
    {
        return view('pages.forms.template-form', $this->forms->templateFormPage($this->organizationId()));
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_type' => 'required|in:'.FormTemplate::TARGET_CLIENT.','.FormTemplate::TARGET_EMPLOYEE,
            'requires_signature' => 'nullable|boolean',
            'is_compliance_required' => 'nullable|boolean',
            'fields' => 'nullable|string',
        ]);

        $validated['requires_signature'] = $request->boolean('requires_signature');
        $validated['is_compliance_required'] = $request->boolean('is_compliance_required');

        if (! empty($validated['fields'])) {
            json_decode($validated['fields']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['fields' => 'Fields must be valid JSON.'])->withInput();
            }
        }

        $this->forms->createTemplate($this->organizationId(), $validated, $request->user());

        return redirect()->route('forms')->with('success', 'Template created.');
    }

    public function editTemplate(int $templateId)
    {
        $data = $this->forms->templateFormPage($this->organizationId(), $templateId);

        if (! $data || ! $data['template']) {
            abort(404);
        }

        return view('pages.forms.template-form', $data);
    }

    public function updateTemplate(Request $request, int $templateId): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_type' => 'required|in:'.FormTemplate::TARGET_CLIENT.','.FormTemplate::TARGET_EMPLOYEE,
            'requires_signature' => 'nullable|boolean',
            'is_compliance_required' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'fields' => 'nullable|string',
        ]);

        $validated['requires_signature'] = $request->boolean('requires_signature');
        $validated['is_compliance_required'] = $request->boolean('is_compliance_required');
        $validated['is_active'] = $request->boolean('is_active');

        if (! empty($validated['fields'])) {
            json_decode($validated['fields']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['fields' => 'Fields must be valid JSON.'])->withInput();
            }
        }

        $this->forms->updateTemplate($this->organizationId(), $templateId, $validated);

        return redirect()->route('forms')->with('success', 'Template updated.');
    }

    public function deactivateTemplate(int $templateId): RedirectResponse
    {
        $this->forms->deactivateTemplate($this->organizationId(), $templateId);

        return redirect()->route('forms')->with('success', 'Template deactivated.');
    }

    public function fill(Request $request, int $templateId)
    {
        $data = $this->forms->fillPage($this->organizationId(), $templateId, $request);

        if (! $data) {
            abort(404);
        }

        return view('pages.forms.fill', $data);
    }

    public function store(Request $request, int $templateId): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'subject_id' => 'required|integer',
            'action' => 'required|in:save,send_signature,sign',
            'fields' => 'nullable|array',
        ]);

        $submission = $this->forms->storeSubmission(
            $this->organizationId(),
            $templateId,
            (int) $validated['subject_id'],
            $validated['fields'] ?? [],
            $request->user(),
            $validated['action'],
        );

        $message = match ($validated['action']) {
            'sign' => 'Form signed and saved to Documents.',
            'send_signature' => 'Form sent for e-signature.',
            default => 'Form draft saved.',
        };

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'submission_id' => $submission->id]);
        }

        return redirect()->route('forms')->with('success', $message);
    }

    public function sign(Request $request, int $submissionId): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'signed_by_name' => 'required|string|max:255',
        ]);

        try {
            $this->forms->signSubmission(
                $this->organizationId(),
                $submissionId,
                $validated['signed_by_name'],
            );
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Form signed and locked.']);
        }

        return redirect()->route('forms')->with('success', 'Form signed and locked.');
    }

    public function void(Request $request, int $submissionId): RedirectResponse
    {
        $validated = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        try {
            $this->forms->voidSubmission(
                $this->organizationId(),
                $submissionId,
                $validated['void_reason'],
                $request->user(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('forms.submissions.show', $submissionId)
            ->with('success', 'Form voided.');
    }

    public function generateDrafts(Request $request): RedirectResponse
    {
        $result = $this->forms->generateMissingComplianceDrafts($this->organizationId());

        if (! $result['agent']) {
            return redirect()->route('forms')->with('error', 'Forms agent is not available for this organization.');
        }

        return redirect()->route('forms')->with(
            'success',
            "Generated {$result['created']} draft(s); skipped {$result['skipped']} already covered."
        );
    }

    public function show(int $submissionId)
    {
        $data = $this->forms->showPage($this->organizationId(), $submissionId);

        if (! $data) {
            abort(404);
        }

        return view('pages.forms.show', $data);
    }

    public function edit(int $submissionId)
    {
        $data = $this->forms->editPage($this->organizationId(), $submissionId);

        if (! $data) {
            abort(404);
        }

        return view('pages.forms.edit', $data);
    }

    public function update(Request $request, int $submissionId): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:save,send_signature,sign',
            'fields' => 'nullable|array',
        ]);

        try {
            $this->forms->updateSubmission(
                $this->organizationId(),
                $submissionId,
                $validated['fields'] ?? [],
                $request->user(),
                $validated['action'],
            );
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $message = match ($validated['action']) {
            'sign' => 'Form signed and saved to Documents.',
            'send_signature' => 'Form sent for e-signature.',
            default => 'Form updated.',
        };

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->route('forms')->with('success', $message);
    }

    public function destroy(int $submissionId): RedirectResponse
    {
        try {
            $this->forms->deleteSubmission($this->organizationId(), $submissionId);
        } catch (\RuntimeException $e) {
            return redirect()->route('forms')->with('error', $e->getMessage());
        }

        return redirect()->route('forms')->with('success', 'Form submission deleted.');
    }

    public function download(int $submissionId): StreamedResponse
    {
        $submission = FormSubmission::query()
            ->when($this->organizationId(), fn ($q) => $q->where('organization_id', $this->organizationId()))
            ->with('document')
            ->findOrFail($submissionId);

        // D9: serve the real signed PDF when it was rendered at signing time.
        $document = $submission->document;

        if ($document && \Illuminate\Support\Facades\Storage::disk($document->disk ?? 'local')->exists($document->path)) {
            return \Illuminate\Support\Facades\Storage::disk($document->disk ?? 'local')
                ->download($document->path, $document->original_filename ?? basename($document->path));
        }

        $content = "Signed form: {$submission->template?->name}\n";
        $content .= "Person: {$submission->subjectName()}\n";
        $content .= "Signed: {$submission->signed_at?->format('Y-m-d H:i')}\n";
        $content .= "By: {$submission->signed_by_name}\n\n";
        $content .= json_encode($submission->field_values, JSON_PRETTY_PRINT);

        $filename = str($submission->template?->slug ?? 'form')->slug().'-'.$submission->id.'.txt';

        return response()->streamDownload(
            fn () => print($content),
            $filename,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    private function organizationId(): ?int
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() ? null : $user?->organization_id;
    }
}
