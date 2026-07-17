<?php

namespace App\Http\Controllers;

use App\Http\Requests\Intake\LogIntakeCallRequest;
use App\Http\Requests\Intake\ScheduleIntakeAssessmentRequest;
use App\Http\Requests\Intake\StoreIntakeRequest;
use App\Http\Requests\Intake\UpdateIntakeRequest;
use App\Http\Requests\Intake\UploadIntakeDocumentRequest;
use App\Models\Intake;
use App\Models\Status;
use App\Services\Intake\IntakeAgentPipelineService;
use App\Services\Intake\IntakeConversionService;
use App\Services\Intake\IntakeEligibilityService;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    public function __construct(
        protected IntakeConversionService $conversion,
        protected IntakeAgentPipelineService $agentPipeline,
    ) {}
    /** Scan-first intake wizard (client review D1): scan → verify → eligibility → create. */
    public function wizard()
    {
        $this->authorize('create', Intake::class);

        return view('pages.intake.wizard', [
            'title' => 'New Intake — Scan First',
            'mcoOptions' => \App\Support\DirectoryMcoOptions::list(),
            'coverageTypes' => \App\Models\CoverageType::all(['id', 'name']),
            'caregivers' => \App\Models\Employee::query()
                ->when(auth()->user()?->organization_id, fn ($q) => $q->where('organization_id', auth()->user()->organization_id))
                ->where('position', 'like', '%Caregiver%')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name']),
            'scanIdUrl' => route('ai.scan-id'),
            'recognizeDocumentUrl' => route('ai.recognize-document'),
        ]);
    }

    /** Step 3 of the wizard — offline eligibility screen + program recommendation (JSON). */
    public function checkEligibility(Request $request, IntakeEligibilityService $eligibility)
    {
        $this->authorize('create', Intake::class);

        $data = $request->validate([
            'dob' => ['nullable', 'date'],
            'member_id' => ['nullable', 'string', 'max:30'],
            'mco_name' => ['nullable', 'string', 'max:100'],
            'plan_name' => ['nullable', 'string', 'max:100'],
            'payer_name' => ['nullable', 'string', 'max:100'],
        ]);

        $checked = $eligibility->check($data);

        return response()->json([
            'ok' => true,
            'eligibility' => $checked,
            'recommendation' => $eligibility->recommendProgram(array_merge($data, $checked)),
        ]);
    }

    public function index()
    {
        $this->authorize('viewAny', Intake::class);

        $intakes = Intake::with('statusRecord')->get();

        return view('pages.intake.index', compact('intakes'), ['title' => 'Intake Management']);
    }

    public function show($id)
    {
        $intake = Intake::withoutGlobalScopes()->with(['statusRecord', 'documents', 'coverageType'])->findOrFail($id);
        $this->authorize('view', $intake);

        return view('pages.intake.show', compact('intake'), ['title' => 'Intake Details']);
    }

    public function print($id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('view', $intake);

        return view('pages.intake.print', compact('intake'), ['title' => 'Clinical Assessment - '.$intake->last_name]);
    }

    public function download($id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('view', $intake);

        $html = view('pages.intake.print', compact('intake'))->render();
        $filename = 'intake-assessment-'.$intake->id.'-'.now()->format('Y-m-d').'.html';

        return response()->streamDownload(
            fn () => print($html),
            $filename,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    public function store(StoreIntakeRequest $request)
    {
        $validated = $request->validated();
        $status = Status::where('entity_type', 'Intake')->where('name', 'New Lead')->first();

        if (isset($validated['scan_data']) && is_string($validated['scan_data'])) {
            $validated['scan_data'] = json_decode($validated['scan_data'], true) ?: null;
        }

        if (isset($validated['scanned_documents']) && is_string($validated['scanned_documents'])) {
            $validated['scanned_documents'] = json_decode($validated['scanned_documents'], true) ?: null;
        }

        $intake = Intake::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id ?? \App\Models\Organization::first()?->id ?? 1,
            'status_id' => $status?->id,
            'status' => 'New',
        ]));

        if ($request->boolean('from_wizard')) {
            $this->agentPipeline->submit($intake);
            $intake->refresh();

            if ($intake->converted_client_id) {
                return redirect()->route('clients.show', $intake->converted_client_id)
                    ->with('success', 'Intake complete — client created and agent follow-ups queued.');
            }

            return redirect()->route('intakes.show', $intake->id)
                ->with('success', 'Intake saved — complete eligibility review or convert when ready.');
        }

        return redirect()->route('intakes.index')->with('success', 'New intake assessment created successfully.');
    }

    public function convert($id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('convert', $intake);

        if ($intake->converted_client_id) {
            return redirect()->back()->with('error', 'This intake has already been converted.');
        }

        try {
            $result = $this->conversion->convert($intake, activateImmediately: true);
            $this->agentPipeline->handOffAfterConversion(
                $result['intake'],
                $result['client'],
                $result['care_detail'],
            );
            $client = $result['client'];
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Intake successfully converted to Client profile.');
    }

    public function update(UpdateIntakeRequest $request, $id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $intake->update($request->validated());

        return redirect()->route('intakes.index')->with('success', 'Intake record updated successfully.');
    }

    public function destroy($id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('delete', $intake);
        $intake->delete();

        return redirect()->route('intakes.index')->with('success', 'Intake record deleted.');
    }

    public function logCall(LogIntakeCallRequest $request, $id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $note = $request->input('note', 'Call attempted - '.now()->format('M d, Y h:i A'));
        $intake->update(['notes' => ($intake->notes ? $intake->notes."\n" : '').'📞 '.$note]);

        return redirect()->back()->with('success', 'Call attempt logged successfully.');
    }

    public function scheduleAssessment(ScheduleIntakeAssessmentRequest $request, $id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $date = $request->input('assessment_date', now()->addDays(3)->format('Y-m-d'));
        $note = '📅 Assessment scheduled for: '.$date;
        $intake->update([
            'status' => 'Contacted',
            'notes' => ($intake->notes ? $intake->notes."\n" : '').$note,
        ]);

        return redirect()->back()->with('success', 'Assessment scheduled and status updated.');
    }

    public function markIneligible($id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('markIneligible', $intake);

        $intake->update([
            'status' => 'Ineligible',
            'notes' => ($intake->notes ? $intake->notes."\n" : '').'❌ Marked as Ineligible on '.now()->format('M d, Y'),
        ]);

        return redirect()->back()->with('success', 'Lead marked as ineligible.');
    }

    public function uploadDocument(UploadIntakeDocumentRequest $request, $id)
    {
        $intake = Intake::withoutGlobalScopes()->findOrFail($id);
        $file = $request->file('file');
        $name = $request->input('name') ?: $file->getClientOriginalName();
        $path = $file->store('intake-documents', 'public');

        \App\Models\Document::create([
            'organization_id' => $intake->organization_id,
            'name' => $name,
            'documentable_id' => $intake->id,
            'documentable_type' => 'App\Models\Intake',
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
            'type' => 'ID',
            'category' => 'General',
            'verification_status' => 'Pending',
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', '"'.$name.'" uploaded successfully.');
    }
}
