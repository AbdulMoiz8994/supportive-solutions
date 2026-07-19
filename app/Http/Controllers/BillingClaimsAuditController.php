<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingClaimsAudit\OverrideBillingBlockRequest;
use App\Http\Requests\BillingClaimsAudit\RecordEobPaymentRequest;
use App\Http\Requests\BillingClaimsAudit\UpdateBillingClaimAuditRateRequest;
use App\Http\Requests\BillingClaimsAudit\UpdateBillingClaimAuditRequest;
use App\Models\BillingClaimAudit;
use App\Services\BillingClaimsAuditService;
use App\Services\Billing\BillingClaimAvailityStatusService;
use App\Services\Billing\BillingClaimGenerateSubmitService;
use App\Support\CsvStream;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingClaimsAuditController extends Controller
{
    public function __construct(
        protected BillingClaimsAuditService $auditService,
        protected BillingClaimAvailityStatusService $availityStatusService,
        protected BillingClaimGenerateSubmitService $generateSubmitService,
    ) {}

    protected function organizationScopeId(): ?int
    {
        $user = auth()->user();

        return $user->isSuperAdmin() ? null : $user->organization_id;
    }

    protected function scopedClaimQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = BillingClaimAudit::query();
        $orgId = $this->organizationScopeId();

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', BillingClaimAudit::class);

        $orgId = $this->organizationScopeId();
        $period = $this->auditService->parsePeriod($request->query('period'));
        $filters = [
            'period' => $period->format('Y-m'),
            'search' => $request->query('search'),
            'program' => $request->query('program'),
            'status' => $request->query('status'),
            'billing_status' => $request->query('billing_status'),
            'audit_status' => $request->query('audit_status'),
            'authorization_status' => $request->query('authorization_status'),
            'coverage_type' => $request->query('coverage_type'),
            'payment_status' => $request->query('payment_status'),
            'issue_type' => $request->query('issue_type'),
            'sort' => $request->query('sort', 'status'),
        ];

        $claims = $this->auditService->paginate($orgId, $filters);
        $summary = $this->auditService->summaryForPeriod($orgId, $period);
        $tabCounts = $this->auditService->tabCounts($orgId, $filters);
        $periodOptions = $this->auditService->periodOptions($period);

        return view('pages.billing-claims-audit.index', [
            'claims' => $claims,
            'summary' => $summary,
            'tabCounts' => $tabCounts,
            'periodOptions' => $periodOptions,
            'filters' => $filters,
            'period' => $period,
            'prevPeriod' => $this->auditService->adjacentPeriod($period, -1),
            'nextPeriod' => $this->auditService->adjacentPeriod($period, 1),
        ], ['title' => 'Billing & Claims Audit']);
    }

    public function show(BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('view', $billing_claims_audit);

        $billing_claims_audit->load(['client.coverageType', 'employee', 'careDetail', 'overrider', 'creator', 'updater']);

        if (! $billing_claims_audit->billing_status) {
            $this->auditService->refreshRecord($billing_claims_audit);
            $billing_claims_audit->refresh();
        }

        return view('pages.billing-claims-audit.show', [
            'claim' => $billing_claims_audit,
            'periodLabel' => $billing_claims_audit->billing_period->format('M Y'),
        ], ['title' => $billing_claims_audit->client?->first_name.' — '.$billing_claims_audit->program_type.' claim']);
    }

    public function aging(Request $request)
    {
        $this->authorize('viewAny', BillingClaimAudit::class);

        $orgId = $this->organizationScopeId();
        $asOf = $this->auditService->parsePeriod($request->query('period'));
        $program = $request->query('program', 'all');
        $programFilter = $program === 'all' ? null : $program;
        $asOfDate = $asOf->copy()->endOfMonth();
        $aging = $this->auditService->agingData($orgId, $asOfDate, $programFilter);
        $overdueClaims = $this->auditService->overduePaginated($orgId, $asOfDate, $programFilter);

        return view('pages.billing-claims-audit.aging', [
            'aging' => $aging,
            'overdueClaims' => $overdueClaims,
            'asOf' => $asOfDate,
            'program' => $program,
            'periodOptions' => $this->auditService->periodOptions($asOf),
        ], ['title' => 'Aging Report']);
    }

    public function downloadDocument(BillingClaimAudit $billing_claims_audit, int $documentIndex)
    {
        $this->authorize('view', $billing_claims_audit);

        if ($documentIndex === 0 && $billing_claims_audit->resolvedPdfPath()) {
            return $this->downloadPdfResponse($billing_claims_audit);
        }

        $documents = $billing_claims_audit->documents ?? [];

        if (! isset($documents[$documentIndex]) || empty($documents[$documentIndex]['path'])) {
            abort(404);
        }

        $document = $documents[$documentIndex];
        $relativePath = str_replace('\\', '/', $document['path']);
        $path = storage_path('app/'.$relativePath);

        if (! is_file($path)) {
            return redirect()
                ->route('billing-claims-audit.show', $billing_claims_audit)
                ->with('warning', 'Document file is not available on disk yet.');
        }

        return response()->download($path, basename($relativePath));
    }

    public function downloadPdf(BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('view', $billing_claims_audit);

        if (! $billing_claims_audit->resolvedPdfPath()) {
            return redirect()
                ->route('billing-claims-audit.show', $billing_claims_audit)
                ->with('warning', 'PDF is not available for this claim yet.');
        }

        return $this->downloadPdfResponse($billing_claims_audit);
    }

    protected function downloadPdfResponse(BillingClaimAudit $claim): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $relativePath = $claim->resolvedPdfPath();
        $path = storage_path('app/'.$relativePath);

        return response()->download($path, basename($relativePath));
    }

    public function exportAging(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', BillingClaimAudit::class);

        $orgId = $this->organizationScopeId();
        $asOf = $this->auditService->parsePeriod($request->query('period'));
        $program = $request->query('program', 'all');
        $programFilter = $program === 'all' ? null : $program;
        $asOfDate = $asOf->copy()->endOfMonth();

        $records = $this->auditService->outstandingRecords($orgId, $asOfDate, $programFilter);

        $rows = $records->map(function (BillingClaimAudit $record) use ($asOfDate) {
            return [
                $record->claim_number,
                trim(($record->client?->first_name ?? '').' '.($record->client?->last_name ?? '')),
                $record->program_type,
                $record->submission_channel,
                $record->billing_period?->format('Y-m'),
                $record->total_amount,
                $record->ageInDays($asOfDate),
                $record->agingBucket($asOfDate),
                $record->statusLabel(),
            ];
        });

        return CsvStream::download(
            'billing-aging-'.$asOf->format('Y-m').'.csv',
            ['Claim Number', 'Client', 'Program', 'Channel', 'Period', 'Amount', 'Age (days)', 'Bucket', 'Status'],
            $rows
        );
    }

    public function escalate(BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('runActions', BillingClaimAudit::class);
        $this->authorize('view', $billing_claims_audit);

        if ($billing_claims_audit->audit_status === BillingClaimAudit::AUDIT_NOT_REVIEWED) {
            $billing_claims_audit->update([
                'audit_status' => BillingClaimAudit::AUDIT_ESCALATED,
                'updated_by' => auth()->id(),
            ]);
        }

        return redirect()
            ->route('billing-claims-audit.show', $billing_claims_audit)
            ->with('success', 'Claim escalated to Workflow Queue for AI follow-up.');
    }

    public function refresh(BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('update', $billing_claims_audit);

        $this->auditService->refreshRecord($billing_claims_audit, auth()->id());

        return redirect()
            ->route('billing-claims-audit.show', $billing_claims_audit)
            ->with('success', 'Authorization, visit, and payment audit data refreshed.');
    }

    public function recordEob(RecordEobPaymentRequest $request, BillingClaimAudit $billing_claims_audit)
    {
        $data = $request->validated();

        if ($request->hasFile('eob_document')) {
            $path = $request->file('eob_document')->store(
                'billing-claims-audit/eob/'.$billing_claims_audit->organization_id,
                'local'
            );
            $data['eob_document_path'] = $path;
        }

        $this->auditService->recordEobPayment($billing_claims_audit, $data, auth()->id());

        return redirect()
            ->route('billing-claims-audit.show', $billing_claims_audit)
            ->with('success', 'EOB / payment data recorded.');
    }

    public function override(OverrideBillingBlockRequest $request, BillingClaimAudit $billing_claims_audit)
    {
        $this->auditService->applyOverride(
            $billing_claims_audit,
            $request->validated('override_reason'),
            auth()->id()
        );

        return redirect()
            ->route('billing-claims-audit.show', $billing_claims_audit)
            ->with('success', 'Billing block overridden — record marked ready to bill.');
    }

    /**
     * Manual billing — Step: Eligibility verified. The agent/staff confirms
     * coverage is active in the payer portal (its own step, before submit).
     * Stamps who + when. Never changes an amount.
     */
    public function verifyEligibility(Request $request, BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('update', $billing_claims_audit);

        if ($billing_claims_audit->isSubmittedOrBeyond()) {
            return back()->with('warning', 'This claim has already been submitted.');
        }

        $validated = $request->validate([
            'eligibility_note' => ['nullable', 'string', 'max:255'],
        ]);

        $billing_claims_audit->eligibility_verified_at = now();
        $billing_claims_audit->eligibility_verified_by = auth()->id();
        if (! empty($validated['eligibility_note'])) {
            $billing_claims_audit->eligibility_note = $validated['eligibility_note'];
        }
        $billing_claims_audit->updated_by = auth()->id();

        $this->appendBillingActivity($billing_claims_audit, 'eligibility_verified', 'Eligibility verified — coverage confirmed active in the payer portal.');
        $billing_claims_audit->save();

        return back()->with('success', 'Eligibility verified — ready to submit.');
    }

    /** Manual billing — clear the eligibility checkoff (undo). */
    public function unverifyEligibility(Request $request, BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('update', $billing_claims_audit);

        if ($billing_claims_audit->isSubmittedOrBeyond()) {
            return back()->with('warning', 'This claim has already been submitted.');
        }

        $billing_claims_audit->eligibility_verified_at = null;
        $billing_claims_audit->eligibility_verified_by = null;
        $billing_claims_audit->eligibility_note = null;
        $billing_claims_audit->updated_by = auth()->id();
        $billing_claims_audit->save();

        return back()->with('success', 'Eligibility check cleared.');
    }

    /**
     * Manual billing — Step: Submitted. The agent/staff has submitted the claim
     * in the payer portal (Office Ally), emailed the invoice (DHS→ASW), or keyed
     * it into Compass (DAAA), and records the confirmation/claim # + date +
     * channel here. Requires eligibility verified first (separate step).
     */
    public function markSubmitted(Request $request, BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('update', $billing_claims_audit);

        if ($billing_claims_audit->isSubmittedOrBeyond()) {
            return back()->with('warning', 'This claim is already marked submitted.');
        }
        if ($billing_claims_audit->isCpBlocked()) {
            return back()->with('warning', 'This claim is held by the CP-01 gate — clear the hold first.');
        }
        if (! $billing_claims_audit->isEligibilityVerified()) {
            return back()->with('warning', 'Verify eligibility before marking the claim submitted.');
        }

        $validated = $request->validate([
            'claim_number' => ['required', 'string', 'max:100'],
            'submitted_on' => ['required', 'date'],
            'submission_channel' => ['required', 'string', 'max:120'],
        ]);

        $billing_claims_audit->claim_number = $validated['claim_number'];
        $billing_claims_audit->submission_channel = $validated['submission_channel'];
        $billing_claims_audit->submitted_at = \Illuminate\Support\Carbon::parse($validated['submitted_on']);
        $billing_claims_audit->submitted_by = auth()->id();
        $billing_claims_audit->billing_status = BillingClaimAudit::BILLING_SUBMITTED;
        $billing_claims_audit->syncClaimStatusFromBillingStatus();
        $billing_claims_audit->updated_by = auth()->id();

        $this->appendBillingActivity(
            $billing_claims_audit,
            'submitted_manual',
            'Submitted via '.$validated['submission_channel'].' — confirmation '.$validated['claim_number'].'.'
        );
        $billing_claims_audit->save();

        return back()->with('success', 'Claim marked submitted via '.$validated['submission_channel'].' (#'.$validated['claim_number'].').');
    }

    /** Append a who+when entry to the claim's activity_log + last_action. */
    protected function appendBillingActivity(BillingClaimAudit $claim, string $event, string $detail): void
    {
        $log = $claim->activity_log ?? [];
        $log[] = [
            'event'  => $event,
            'detail' => $detail,
            'by'     => auth()->user()?->name ?? 'Staff',
            'at'     => now()->toIso8601String(),
        ];
        $claim->activity_log = $log;
        $claim->last_action = $detail;
    }

    public function downloadEob(BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('view', $billing_claims_audit);

        if (! $billing_claims_audit->eob_document_path) {
            abort(404);
        }

        $relativePath = $billing_claims_audit->eob_document_path;
        $expectedPrefix = 'billing-claims-audit/eob/'.$billing_claims_audit->organization_id.'/';

        if (! str_starts_with(str_replace('\\', '/', $relativePath), $expectedPrefix)) {
            abort(404);
        }

        $path = storage_path('app/'.$relativePath);

        if (! is_file($path)) {
            return redirect()
                ->route('billing-claims-audit.show', $billing_claims_audit)
                ->with('warning', 'EOB file is not available on disk.');
        }

        return response()->download($path, basename($relativePath));
    }

    public function updateRate(UpdateBillingClaimAuditRateRequest $request, BillingClaimAudit $billing_claims_audit)
    {
        $this->auditService->updateRate(
            $billing_claims_audit,
            (float) $request->validated('hourly_rate'),
            auth()->id()
        );

        return redirect()
            ->route('billing-claims-audit.show', $billing_claims_audit)
            ->with('success', 'Billing rate updated and amount recalculated.');
    }

    public function update(UpdateBillingClaimAuditRequest $request, BillingClaimAudit $billing_claims_audit)
    {
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $billing_claims_audit->update($data);

        return redirect()
            ->route('billing-claims-audit.show', $billing_claims_audit)
            ->with('success', 'Claim audit record updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', BillingClaimAudit::class);

        $orgId = $this->organizationScopeId();
        $period = $this->auditService->parsePeriod($request->query('period'));
        $filters = [
            'period' => $period->format('Y-m'),
            'search' => $request->query('search'),
            'program' => $request->query('program'),
            'status' => $request->query('status'),
        ];

        $records = $this->auditService->filteredQuery($orgId, $filters)->get();
        $filename = 'billing-claims-audit-'.$period->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Claim Number', 'Client', 'Program', 'Period', 'Hours', 'Rate', 'Amount', 'Channel', 'Status',
            ]);

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->claim_number,
                    trim(($record->client?->first_name ?? '').' '.($record->client?->last_name ?? '')),
                    $record->program_type,
                    $record->billing_period->format('M Y'),
                    $record->total_hours,
                    $record->hourly_rate,
                    $record->total_amount,
                    $record->submission_channel,
                    $record->statusLabel(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function generateSubmit(Request $request)
    {
        $this->authorize('runActions', BillingClaimAudit::class);

        $period = $this->auditService->parsePeriod($request->input('period'));
        $result = $this->generateSubmitService->run($this->organizationScopeId(), $period, auth()->user());

        $redirect = redirect()
            ->route('billing-claims-audit.index', ['period' => $period->format('Y-m')]);

        if (! empty($result['errors'])) {
            $redirect = $redirect->with('submission_errors', $result['errors']);
        }

        return $redirect->with($result['flash_type'], $result['flash']);
    }

    public function submitClaim(BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('update', $billing_claims_audit);

        $result = $this->generateSubmitService->submitSingle($billing_claims_audit, auth()->user());

        return redirect()
            ->route('billing-claims-audit.show', $billing_claims_audit)
            ->with($result['flash_type'], $result['flash']);
    }

    public function sigmaPortal(BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('view', $billing_claims_audit);

        $url = app(\App\Services\Billing\SigmaPortalBillingService::class)->portalUrl();

        return redirect()->away($url);
    }

    public function refreshAvailityStatus(BillingClaimAudit $billing_claims_audit)
    {
        $this->authorize('update', $billing_claims_audit);

        if (! $this->availityStatusService->canSync($billing_claims_audit)) {
            return redirect()
                ->route('billing-claims-audit.show', $billing_claims_audit)
                ->with('warning', 'This claim is not routed through Availity.');
        }

        try {
            $result = $this->availityStatusService->sync($billing_claims_audit, auth()->id());
        } catch (\Throwable $exception) {
            return redirect()
                ->route('billing-claims-audit.show', $billing_claims_audit)
                ->with('warning', 'Availity status check failed: '.$exception->getMessage());
        }

        if (! $result['success']) {
            return redirect()
                ->route('billing-claims-audit.show', $billing_claims_audit)
                ->with('warning', 'Availity returned an error: '.($result['message'] ?? 'Unknown error'));
        }

        return redirect()
            ->route('billing-claims-audit.show', $billing_claims_audit)
            ->with('success', 'Availity claim status updated: '.$billing_claims_audit->fresh()->availityStatusLabel().'.');
    }

    public function refreshAvailityStatusBatch(Request $request)
    {
        $this->authorize('runActions', BillingClaimAudit::class);

        $period = $this->auditService->parsePeriod($request->input('period'));
        $counts = $this->availityStatusService->syncPeriod($this->organizationScopeId(), $period, auth()->id());

        $message = "Availity status refreshed — {$counts['synced']} updated";
        if ($counts['failed'] > 0) {
            $message .= ", {$counts['failed']} failed";
        }
        if ($counts['skipped'] > 0) {
            $message .= ", {$counts['skipped']} skipped";
        }
        $message .= '.';

        return redirect()
            ->route('billing-claims-audit.index', ['period' => $period->format('Y-m')])
            ->with($counts['failed'] > 0 && $counts['synced'] === 0 ? 'warning' : 'success', $message);
    }

    public function chaseOverdue(Request $request)
    {
        $this->authorize('runActions', BillingClaimAudit::class);

        $orgId = $this->organizationScopeId();
        $asOf = $this->auditService->parsePeriod($request->input('period', now()->format('Y-m')));
        $program = $request->input('program', 'all');
        $programFilter = $program === 'all' ? null : $program;
        $count = $this->auditService->escalateOverdueClaims($orgId, $asOf->copy()->endOfMonth(), $programFilter, auth()->id());

        return redirect()
            ->route('billing-claims-audit.aging', ['period' => $asOf->format('Y-m'), 'program' => $program])
            ->with('success', $count > 0
                ? "Chase overdue triggered for {$count} bills — routed to Workflow Queue."
                : 'No overdue bills to chase.');
    }
}
