<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payroll\BuildPayrollBatchRequest;
use App\Http\Requests\Payroll\ReleasePayrollHoldRequest;
use App\Http\Requests\Payroll\UpdatePayrollHoldRequest;
use App\Http\Requests\Payroll\UpdatePayrollWageRequest;
use App\Jobs\SubmitPayrollClaimJob;
use App\Mail\PayrollApprovalNotification;
use App\Models\PayRecord;
use App\Services\Payroll\AccountantsWorldClient;
use App\Services\Payroll\AccountantsWorldEmployeeSetupService;
use App\Services\Payroll\AccountantsWorldPayrollSyncService;
use App\Services\PayrollBatchService;
use App\Services\PayrollDocumentService;
use App\Services\PayrollExportService;
use App\Services\PayrollAuditService;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService,
        protected PayrollBatchService $batchService,
        protected PayrollExportService $exportService,
        protected PayrollDocumentService $documentService,
        protected PayrollAuditService $auditService,
        protected AccountantsWorldClient $accountantsWorldClient,
        protected AccountantsWorldEmployeeSetupService $accountantsWorldSetupService,
        protected AccountantsWorldPayrollSyncService $payrollSyncService,
    ) {}

    protected function organizationScopeId(): ?int
    {
        $user = auth()->user();

        return $user->isSuperAdmin() ? null : $user->organization_id;
    }

    protected function filterPayload(Request $request): array
    {
        return $this->payrollService->normalizeFilters([
            'period'         => $request->query('period', $request->input('period')),
            'search'         => $request->query('search'),
            'client_search'  => $request->query('client_search'),
            'status'         => $request->query('status'),
            'caregiver_type' => $request->query('caregiver_type'),
            'live_in'        => $request->boolean('live_in'),
            'evv_exempt'     => $request->boolean('evv_exempt'),
            'in_grace'       => $request->boolean('in_grace'),
            'held'           => $request->boolean('held'),
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', PayRecord::class);

        $orgId = $this->organizationScopeId();
        $filters = $this->filterPayload($request);
        $period = $this->payrollService->parsePeriod($filters['period']);
        $indexData = $this->payrollService->getIndexData($orgId, $filters);
        $records = $this->payrollService->paginate($orgId, $filters);

        return view('pages.payroll.index', array_merge($indexData, [
            'records'                  => $records,
            'filters'                  => $filters,
            'prevPeriod'               => $this->payrollService->adjacentPeriod($period, -1),
            'nextPeriod'               => $this->payrollService->adjacentPeriod($period, 1),
            'batchOrganizationOptions' => auth()->user()->isSuperAdmin()
                ? $this->payrollService->organizationsForPeriod($period)
                : collect(),
        ]), ['title' => 'Payroll']);
    }

    public function show(PayRecord $payRecord)
    {
        $this->authorize('view', $payRecord);

        return view('pages.payroll.show', $this->payrollService->getShowData($payRecord), [
            'title' => ($payRecord->employee?->name ?? 'Caregiver').' — Payroll',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', PayRecord::class);

        return $this->exportService->export(
            $this->organizationScopeId(),
            $this->filterPayload($request),
            $request->user()
        );
    }

    public function downloadStub(PayRecord $payRecord)
    {
        $this->authorize('downloadStub', $payRecord);

        $this->auditService->logStubAccess($payRecord, auth()->user());

        return $this->documentService->downloadResponse($payRecord);
    }

    public function updateWage(UpdatePayrollWageRequest $request, PayRecord $payRecord)
    {
        $this->payrollService->updateWage(
            $payRecord,
            (float) $request->validated('hourly_wage'),
            $request->user()
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Hourly wage updated and gross pay recalculated.']);
        }

        return back()->with('success', 'Hourly wage updated and gross pay recalculated.');
    }

    public function applyHold(UpdatePayrollHoldRequest $request, PayRecord $payRecord)
    {
        $this->payrollService->applyHold(
            $payRecord,
            $request->validated('hold_reason'),
            $request->user()
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Payroll hold applied.']);
        }

        return back()->with('success', 'Payroll hold applied.');
    }

    public function buildBatch(BuildPayrollBatchRequest $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $period = $this->payrollService->parsePeriod($request->input('period', $request->query('period')));

        try {
            $orgId = $this->payrollService->resolveBatchOrganizationId(
                $request->user(),
                $period,
                $request->integer('organization_id') ?: null
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('warning', $e->getMessage());
        }

        $batch = $this->batchService->buildBatch(
            $orgId,
            $period,
            $request->user(),
            $request->input('record_ids', [])
        );

        $message = "Batch built with {$batch->record_count} caregiver(s) — total gross \${$batch->total_gross}.";

        if ($request->expectsJson()) {
            return response()->json([
                'message'      => $message,
                'batch_id'     => $batch->id,
                'record_count' => $batch->record_count,
                'total_gross'  => $batch->total_gross,
            ]);
        }

        return back()->with('success', $message);
    }

    public function releaseHold(ReleasePayrollHoldRequest $request, PayRecord $payRecord)
    {
        $this->payrollService->releaseHold($payRecord, $request->user(), $request->input('note'));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Hold released. Record re-evaluated for batch eligibility.']);
        }

        return back()->with('success', 'Hold released. Record re-evaluated for batch eligibility.');
    }

    /**
     * Show all batches pending approval.
     */
    public function batchQueue(Request $request)
    {
        $this->authorize('viewAny', PayRecord::class);

        $batches = \App\Models\PayrollBatch::query()
            ->when($this->organizationScopeId(), fn ($q, $id) => $q->where('organization_id', $id))
            ->with(['builder', 'approver', 'payRecords.employee'])
            ->orderByDesc('built_at')
            ->get();

        $orgId = $this->organizationScopeId();
        $awQueueFilters = [
            'search' => $request->query('aw_search'),
            'context' => $request->query('aw_context'),
            'sort' => $request->query('aw_sort', 'recent'),
        ];
        $awaitingAwSetup = $this->accountantsWorldSetupService->paginateAwaitingSetup($orgId, array_filter([
            'search' => $awQueueFilters['search'],
            'context' => $awQueueFilters['context'] && $awQueueFilters['context'] !== 'all'
                ? $awQueueFilters['context']
                : null,
            'sort' => $awQueueFilters['sort'],
        ]));
        $awEligibleEmployees = $this->accountantsWorldSetupService->listEligibleForSetup($orgId);

        return view('pages.payroll.batch-queue', compact('batches', 'awaitingAwSetup', 'awEligibleEmployees', 'awQueueFilters'), ['title' => 'Payroll Approval Queue']);
    }

    /**
     * Approve a payroll batch — marks it as approved and notifies accountant.
     */
    public function approveBatch(Request $request, \App\Models\PayrollBatch $batch)
    {
        $this->authorize('approveBatch', PayRecord::class);

        $request->validate(['note' => 'nullable|string|max:500']);

        // Apply billing-hold rule: if any caregiver has unpaid billing claims
        // from the previous month, hold their record and flag it.
        $this->applyBillingHoldRule($batch);

        $batch->update([
            'approval_status' => 'approved',
            'approved_by'     => $request->user()->id,
            'approved_at'     => now(),
            'approval_note'   => $request->input('note'),
        ]);

        // Mark all non-held pay records in this batch as "Ready" (eligible to send)
        $batch->payRecords()
            ->whereNotIn('status', ['Held - review', 'Held - billing'])
            ->update(['status' => 'Ready']);

        $batch->load(['payRecords', 'organization']);
        $heldCount = $batch->payRecords->filter(fn (PayRecord $record) => str_starts_with((string) $record->status, 'Held'))->count();
        $readyCount = $batch->payRecords->count() - $heldCount;

        if ($this->notifyAccountant($batch, $request->user(), $readyCount, $heldCount)) {
            $batch->update(['accountant_notified_at' => now()]);
        }

        $this->dispatchAvailityClaimsForBatch($batch);

        return back()->with('success', "Batch #{$batch->id} approved — {$batch->record_count} caregivers ready for AccountantsWorld export.");
    }

    /**
     * Sync an approved batch to AccountantsWorld via the Payroll API.
     * Append ?format=csv to download a local CSV backup instead.
     */
    public function exportBatch(Request $request, \App\Models\PayrollBatch $batch)
    {
        $this->authorize('export', PayRecord::class);

        if (! $batch->isApproved() && $batch->approval_status !== 'exported') {
            return redirect()->route('payroll.batch-queue')->with('warning', 'Batch must be approved before it can be exported.');
        }

        if ($batch->approval_status === 'exported' && $request->query('format') !== 'csv') {
            if (! $request->boolean('force')) {
                return redirect()->route('payroll.batch-queue')->with(
                    'warning',
                    'This batch is already synced to AccountantsWorld. Append ?force=1 to sync again, or use ?format=csv for a CSV backup.'
                );
            }
        }

        if ($request->query('format') === 'csv') {
            $filters = ['period' => $batch->period_key, 'batch_id' => $batch->id];

            return $this->exportService->export(
                $batch->organization_id,
                $filters,
                $request->user()
            );
        }

        $result = $this->payrollSyncService->syncBatch($batch, $request->user());

        if (! $result['success']) {
            return redirect()->route('payroll.batch-queue')->with('warning', $result['message']);
        }

        return redirect()->route('payroll.batch-queue')->with('success', $result['message']);
    }

    /**
     * Create a caregiver in AccountantsWorld directly from the platform.
     */
    public function createAccountantsWorldEmployee(Request $request)
    {
        $this->authorize('export', PayRecord::class);

        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'aw_first_name'  => 'required|string|max:50',
            'aw_last_name'   => 'required|string|max:50',
            'aw_ssn'         => 'required|string|size:9',
            'aw_pay_rate'    => 'required|numeric|min:0',
            'aw_pay_type'    => 'required|in:hourly,salary',
            'aw_dept'        => 'nullable|string|max:50',
        ]);

        $employee = \App\Models\Employee::findOrFail($validated['employee_id']);

        $result = $this->accountantsWorldSetupService->createFromForm($employee, $validated);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('warning', $result['message']);
    }

    /**
     * Retry a failed AccountantsWorld employee setup using saved form data.
     */
    public function retryAccountantsWorldEmployee(Request $request, \App\Models\Employee $employee)
    {
        $this->authorize('export', PayRecord::class);

        if ($this->organizationScopeId() && $employee->organization_id !== $this->organizationScopeId()) {
            abort(404);
        }

        $result = $this->accountantsWorldSetupService->retry($employee);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('warning', $result['message']);
    }

    /**
     * Mark a caregiver as manually added in AccountantsWorld (e.g. created in the AW portal).
     */
    public function resolveAccountantsWorldEmployee(Request $request, \App\Models\Employee $employee)
    {
        $this->authorize('export', PayRecord::class);

        if ($this->organizationScopeId() && $employee->organization_id !== $this->organizationScopeId()) {
            abort(404);
        }

        $validated = $request->validate([
            'aw_employee_id' => 'nullable|string|max:100',
            'verify' => 'nullable|boolean',
        ]);

        if ($request->boolean('verify')) {
            $result = $this->accountantsWorldSetupService->verifyAndMarkSynced(
                $employee,
                $validated['aw_employee_id'] ?? null
            );

            if ($result['success']) {
                return back()->with('success', $result['message']);
            }

            return back()->with('warning', $result['message']);
        }

        $this->accountantsWorldSetupService->markManuallySynced($employee, $validated['aw_employee_id'] ?? null);

        $name = trim("{$employee->first_name} {$employee->last_name}");

        return back()->with('success', "{$name} marked as synced with AccountantsWorld (verification skipped).");
    }

    /**
     * Apply billing-hold rule: hold caregivers whose billing claims from the
     * previous period are not yet processed/paid.
     */
    private function applyBillingHoldRule(\App\Models\PayrollBatch $batch): void
    {
        $prevPeriod = \Carbon\Carbon::parse($batch->period_key.'-01')->subMonth()->format('Y-m');
        $holdLabel  = \Carbon\Carbon::parse($prevPeriod.'-01')->format('F Y');

        $batch->payRecords->each(function (PayRecord $record) use ($prevPeriod, $holdLabel) {
            if (! $record->employee_id) {
                return;
            }

            // Any billing claim from the previous month linked to any client this
            // caregiver serves, where the claim isn't in a paid/complete status.
            $hasUnpaidBilling = \App\Models\Billing::query()
                ->whereIn('client_id', function ($sub) use ($record) {
                    $sub->select('client_id')
                        ->from('client_employee')
                        ->where('employee_id', $record->employee_id);
                })
                ->where(function ($q) use ($prevPeriod) {
                    $q->where('period_start', 'like', $prevPeriod.'%')
                      ->orWhere('period_end', 'like', $prevPeriod.'%');
                })
                ->whereNotIn('status', ['paid', 'Paid', 'paid_in_full', 'complete'])
                ->exists();

            if ($hasUnpaidBilling) {
                $record->update([
                    'status'      => 'Held - review',
                    'hold_reason' => "Billing claim from {$holdLabel} not yet processed — pay held pending billing resolution.",
                ]);
            }
        });
    }

    protected function notifyAccountant(
        \App\Models\PayrollBatch $batch,
        \App\Models\User $approvedBy,
        int $readyCount,
        int $heldCount
    ): bool {
        $email = config('payroll.accountant_email');

        if (! $email) {
            Log::warning('Payroll batch approved but PAYROLL_ACCOUNTANT_EMAIL is not configured.', [
                'batch_id' => $batch->id,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send(new PayrollApprovalNotification(
                $batch,
                $approvedBy,
                $readyCount,
                $heldCount
            ));
        } catch (\Throwable $exception) {
            Log::error('Failed to send payroll accountant notification.', [
                'batch_id' => $batch->id,
                'email'    => $email,
                'message'  => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Queue Availity claim submissions for eligible MICH pay records in an approved batch.
     */
    protected function dispatchAvailityClaimsForBatch(\App\Models\PayrollBatch $batch): void
    {
        $batch->loadMissing('payRecords');

        $heldStatuses = ['Held - review', 'Held - billing'];

        foreach ($batch->payRecords as $record) {
            if (in_array($record->status, $heldStatuses, true)) {
                continue;
            }

            if ($record->program_tag !== 'MICH') {
                continue;
            }

            SubmitPayrollClaimJob::dispatch($record->id);
        }
    }
}
