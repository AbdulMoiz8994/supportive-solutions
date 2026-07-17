<?php

namespace App\Http\Controllers;

use App\Models\BackgroundCheck;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Document;
use App\Models\Employee;
use App\Models\FormSubmission;
use App\Models\FormTemplate;

class ComplianceController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Document::class);

        $allDocuments = Document::with(['documentable'])->get();

        $expired = $allDocuments->filter(fn ($doc) => $doc->isExpired());
        $expiringSoon = $allDocuments->filter(fn ($doc) => $doc->isExpiringSoon());
        $pendingVerification = $allDocuments->where('verification_status', 'Pending');

        // ── Monthly compliance cycle (agency-wide tracker) ──────────────────
        $cycleLabel = now()->format('F Y');

        // Shared definition with Reports (RegistryMetricsService::complianceFormStats)
        // so monthly form counts match on both pages (client review item A5).
        $orgId = auth()->user()?->organization_id;
        $formStats = app(\App\Services\RegistryMetricsService::class)
            ->complianceFormStats($orgId);
        $submitted = $formStats['received_client_ids']->flip();

        // Agency-wide client roster — same scope as complianceFormStats(), not LocationScope.
        $clients = Client::withoutGlobalScopes()
            ->with(['coverageType', 'caregiverAssignments', 'employees'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->get();
        $pastCutoff = now()->day > 5; // cycle closes early in the month

        $tracker = $clients->map(function (Client $client) use ($submitted, $pastCutoff) {
            $caregiver = $client->primary_caregiver;
            $received = $submitted->has($client->id);

            return [
                'client'    => trim($client->first_name.' '.$client->last_name) ?: 'Client #'.$client->id,
                'initials'  => strtoupper(mb_substr($client->first_name ?: 'C', 0, 1).mb_substr($client->last_name ?: '', 0, 1)),
                'caregiver' => $caregiver ? trim($caregiver->first_name.' '.$caregiver->last_name) : '—',
                'program'   => $client->program_label,
                'program_display' => $client->program_display,
                'received'  => $received,
                'late'      => ! $received && $pastCutoff,
            ];
        })->values();

        $late = $tracker->where('late', true)->count();
        $received = $formStats['received'];
        $total = $formStats['total'];

        $cycleStart = now()->startOfMonth();
        $cycleEnd = now()->endOfMonth()->endOfDay();
        $wellnessCalls = Communication::withoutGlobalScopes()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('channel', Communication::CHANNEL_CALL)
            ->whereJsonContains('metadata->wellness_call', true)
            ->whereBetween('created_at', [$cycleStart, $cycleEnd])
            ->count();

        $monthlyKpis = [
            'total'        => $total,
            'received'     => $received,
            'pending'      => max(0, $total - $received - $late),
            'late'         => $late,
            'received_pct' => $formStats['received_pct'],
            'wellness_calls' => $wellnessCalls,
        ];

        // ── Document Hub needs-attention (real counts) ──────────────────────
        try {
            $ichatExpiring = BackgroundCheck::where('type', 'ICHAT')
                ->whereNotNull('next_due')
                ->whereBetween('next_due', [now(), now()->copy()->addDays(30)])
                ->count();
        } catch (\Throwable $e) {
            $ichatExpiring = 0;
        }

        $needsAttention = [
            'expired'        => $expired->count(),
            'pending_review' => $pendingVerification->count(),
            'expiring'       => $expiringSoon->count(),
            'ichat_expiring' => $ichatExpiring,
            'signed_forms'   => FormSubmission::query()
                ->where('status', FormSubmission::STATUS_SIGNED)
                ->whereHas('template', fn ($q) => $q->where('is_compliance_required', true))
                ->count(),
        ];

        $signedComplianceForms = FormSubmission::query()
            ->with(['template', 'document'])
            ->where('status', FormSubmission::STATUS_SIGNED)
            ->whereHas('template', fn ($q) => $q->where('is_compliance_required', true))
            ->latest('signed_at')
            ->limit(25)
            ->get();

        $clientSubjects = Client::query()
            ->select(['id', 'first_name', 'last_name', 'member_id'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'label' => trim($client->first_name.' '.$client->last_name) ?: 'Client #'.$client->id,
                'meta' => $client->member_id ? 'Medicaid: '.$client->member_id : null,
            ])
            ->values();

        $caregiverSubjects = Employee::query()
            ->where('position', 'Caregiver')
            ->select(['id', 'first_name', 'last_name', 'position'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'label' => trim($employee->first_name.' '.$employee->last_name) ?: 'Caregiver #'.$employee->id,
                'meta' => $employee->position,
            ])
            ->values();

        return view('pages.compliance.index', compact(
            'expired', 'expiringSoon', 'pendingVerification', 'clientSubjects', 'caregiverSubjects',
            'cycleLabel', 'tracker', 'monthlyKpis', 'needsAttention', 'signedComplianceForms'
        ), [
            'title' => 'Compliance & Documents',
        ]);
    }

    public function auditIndex()
    {
        $orgId = auth()->user()->organization_id;

        $activities = \App\Models\ActivityLog::with('user')
            ->where('organization_id', $orgId)
            ->latest()
            ->paginate(20);

        return view('pages.audit.index', [
            'title' => 'Clinical Audit Trail',
            'activities' => $activities,
        ]);
    }
}
