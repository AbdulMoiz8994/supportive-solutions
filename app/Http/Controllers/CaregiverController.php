<?php

namespace App\Http\Controllers;

use App\Http\Requests\Caregiver\StoreCaregiverRequest;
use App\Models\Employee;
use App\Models\Client;
use App\Models\BackgroundCheck;
use App\Models\CaregiverAssignment;
use App\Models\CaregiverNote;
use App\Models\CaregiverAuditLog;
use App\Services\RegistryMetricsService;
use App\Services\RegistryExportService;
use App\Support\CaregiverRegistryMetrics;
use App\Support\TabbedPageTitle;
use Illuminate\Http\Request;

class CaregiverController extends Controller
{
    public function __construct(
        protected RegistryMetricsService $registryMetrics,
        protected RegistryExportService $registryExport,
    ) {}

    /**
     * Caregiver Registry — Live Dashboard.
     */
    public function index(Request $request)
    {
        $caregivers = $this->registryMetrics->caregivers();
        $kpis = $this->registryMetrics->caregiverStats($caregivers);
        $rows = $caregivers->map(function (Employee $c) {
            $flags = CaregiverRegistryMetrics::rowFlags($c);
            $checks = $c->backgroundChecks;
            $flag = $checks->firstWhere('status', 'Flagged');
            $ichat = $checks->first(fn ($b) => $b->type === 'ICHAT' && $b->next_due && $b->next_due->lte(now()->addDays(30)) && $b->next_due->gte(now()));
            $enroll = $checks->whereIn('status', ['Enrolling', 'Submitted'])->count();

            if ($flag) {
                $checkLabel = 'Flag — verify';
                $checkTone = 'flag';
            } elseif ($ichat) {
                $checkLabel = 'ICHAT due '.now()->diffInDays($ichat->next_due).'d';
                $checkTone = 'due';
            } elseif ($c->onboarding_status === 'Pending onboarding' || $enroll > 0) {
                $checkLabel = $enroll > 0 ? 'In progress' : 'Enrolling';
                $checkTone = 'progress';
            } else {
                $checkLabel = 'All clear';
                $checkTone = 'clear';
            }

            $assignment = $c->assignments->firstWhere('status', 'Active') ?? $c->assignments->first();
            $served = $c->assignments->map(fn ($a) => optional($a->client)->first_name.' '.optional($a->client)->last_name)->filter()->unique()->implode(', ');
            $lastForm = $c->complianceForms->sortByDesc('period')
                ->first(fn ($form) => in_array($form->status, [\App\Models\ComplianceForm::STATUS_SUBMITTED, \App\Models\ComplianceForm::STATUS_VERIFIED], true));
            $dueForm = $c->complianceForms->firstWhere('status', 'Due') ?? $c->complianceForms->firstWhere('status', 'Awaiting');

            return array_merge($flags, [
                'id' => $c->id,
                'name' => $c->name,
                'ssn' => $c->ssn_last4 ? 'SSN ••••'.$c->ssn_last4 : 'SSN ••••----',
                'dob' => $c->date_of_birth ? $c->date_of_birth->format('m/d/Y').' ('.$c->date_of_birth->age.')' : '—',
                'served' => $served ?: '—',
                'program' => $flags['program'],
                'checkLabel' => $checkLabel,
                'checkTone' => $checkTone,
                'liveIn' => $flags['live_in'],
                'evv' => $flags['live_in'] ? 'Live-in' : 'HHAeXchange',
                'lastComp' => ($lastForm->period_label ?? '—').($dueForm ? ' · '.str_replace(' 2026', '', $dueForm->period_label).' due' : ''),
                'status' => $flags['status'],
                'type' => $flags['type'],
            ]);
        })->values();

        return view('pages.caregivers.index', [
            'caregivers' => $caregivers,
            'rows' => $rows,
            'kpis' => $kpis,
            'title' => 'Caregiver Registry',
        ]);
    }

    /**
     * New Caregiver Onboarding wizard.
     */
    public function create(Request $request)
    {
        $clients = Client::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'address', 'county', 'member_id']);
        $fromClient = $request->query('client_id') ? Client::find($request->query('client_id')) : null;

        return view('pages.caregivers.create', [
            'clients'    => $clients,
            'fromClient' => $fromClient,
            'title'      => 'New Caregiver Onboarding',
        ]);
    }

    /**
     * Persist a new caregiver from the wizard.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => 'nullable|email',
            'phone'       => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'gender'      => 'nullable|string|max:30',
            'ssn_last4'   => 'nullable|string|max:11',
            'address'     => 'nullable|string|max:255',
            'county'      => 'nullable|string|max:100',
            'preferred_language' => 'nullable|string|max:100',
            'needs_accommodations' => 'nullable',
            'caregiver_type'  => 'nullable|string|max:50',
            'relationship_to_client' => 'nullable|string|max:100',
            'prior_experience' => 'nullable',
            'years_experience' => 'nullable|string|max:20',
            'notes'           => 'nullable|string',
            'services'        => 'nullable|array',
            'client_id'       => 'nullable|exists:clients,id',
            'lives_with_client' => 'nullable',
            'hourly_wage'     => 'nullable|numeric',
            'pay_type'        => 'nullable|string|max:50',
            'pay_schedule'    => 'nullable|string|max:60',
            'w4_filing_status'=> 'nullable|string|max:60',
            'insurance_coverage' => 'nullable|string|max:120',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'is_18_plus' => 'nullable',
            'is_work_eligible' => 'nullable',
            'has_background_check' => 'nullable',
        ]);

        $liveIn = $request->boolean('lives_with_client');

        // Stamp the active location so the new profile stays visible under the
        // location filter and is findable by show()/update() (LocationScope).
        // Without this the redirect to caregivers.show 404s when a location is selected.
        $locationId = session('selected_location_id');

        $caregiver = Employee::create(array_merge($validated, [
            'position'             => 'Caregiver',
            'status'               => 'Pending onboarding',
            'onboarding_status'    => 'Pending onboarding',
            'organization_id'      => auth()->user()->organization_id ?? 1,
            'location_id'          => $locationId,
            'needs_accommodations' => $request->boolean('needs_accommodations'),
            'prior_experience'     => $request->boolean('prior_experience'),
            'is_18_plus'           => $request->boolean('is_18_plus'),
            'is_work_eligible'     => $request->boolean('is_work_eligible'),
            'has_background_check' => $request->boolean('has_background_check'),
            'lives_with_client'    => $liveIn,
            'live_in'              => $liveIn,
            'evv_exempt'           => $liveIn,
            'application_signed_at'=> now(),
            'onboarded_by'         => auth()->user()->first_name ?? 'Front desk',
            'pay_type'             => $validated['pay_type'] ?? 'W-2 · hourly',
        ]));

        // Kick off the four background checks on consent
        if ($request->boolean('has_background_check')) {
            foreach ([
                ['CHAMPS', 'CHAMPS', 'One-time at hiring + monitor'],
                ['ICHAT', 'ICHAT', 'Annual'],
                ['SAM', 'SAM.gov', 'Monthly (free API)'],
                ['OIG', 'OIG LEIE', 'Monthly (free download)'],
            ] as [$type, $label, $cadence]) {
                BackgroundCheck::create([
                    'organization_id' => $caregiver->organization_id,
                    'employee_id'     => $caregiver->id,
                    'type'            => $type,
                    'label'           => $label,
                    'cadence'         => $cadence,
                    'status'          => 'Enrolling',
                ]);
            }
        }

        // Assign the client if chosen
        if (!empty($validated['client_id'])) {
            CaregiverAssignment::create([
                'organization_id'  => $caregiver->organization_id,
                'employee_id'      => $caregiver->id,
                'client_id'        => $validated['client_id'],
                'relationship'     => $validated['relationship_to_client'] ?? null,
                'live_in'          => $liveIn,
                'evv_status'       => $liveIn ? 'Exempt (live-in)' : 'Active',
                'status'           => 'Active',
                'assigned_since'   => now(),
            ]);
            $caregiver->clients()->syncWithoutDetaching([$validated['client_id']]);
        }

        CaregiverNote::create([
            'organization_id' => $caregiver->organization_id,
            'employee_id'     => $caregiver->id,
            'author_name'     => auth()->user()->first_name . ' ' . auth()->user()->last_name,
            'author_role'     => 'Front desk',
            'author_type'     => 'human',
            'tag'             => 'Activity',
            'body'            => 'Application & policies signed; caregiver profile created (status: Pending onboarding).',
            'noted_at'        => now(),
        ]);

        $this->audit($caregiver, 'Record created', 'Caregiver profile', null, null,
            'Application signed · checks consent · status Pending onboarding', 'App (web)');

        return redirect()->route('caregivers.show', $caregiver->id)
            ->with('success', 'Caregiver profile created. Status: Pending onboarding.');
    }

    /**
     * The 11-tab Caregiver Profile.
     */
    public function show($id)
    {
        $caregiver = $this->findCaregiver($id, [
            'assignments.client',
            'backgroundChecks',
            'complianceForms.client',
            'payRecords',
            'communications',
            'caregiverNotes',
            'auditLogs',
            'documents',
            'schedules.client',
            'clients.careDetails',
        ]);

        $assignment = $caregiver->assignments->firstWhere('status', 'Active') ?? $caregiver->assignments->first();
        $servedClient = $assignment?->client;
        $documentChecklist = app(\App\Services\DocumentChecklistService::class)->forCaregiver($caregiver);

        return view('pages.caregivers.show', [
            'caregiver'    => $caregiver,
            'assignment'   => $assignment,
            'servedClient' => $servedClient,
            'documentChecklist' => $documentChecklist,
            'title'        => TabbedPageTitle::caregiver($caregiver->name, request('tab')),
        ]);
    }

    /**
     * Inline edits from the profile (Personal & Employment, etc.).
     */
    public function update(Request $request, $id)
    {
        $caregiver = $this->findCaregiver($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name'  => 'sometimes|required|string|max:255',
            'email'      => 'nullable|email',
            'phone'      => 'nullable|string|max:30',
            'gender'     => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date',
            'address'    => 'nullable|string|max:255',
            'county'     => 'nullable|string|max:100',
            'preferred_language' => 'nullable|string|max:100',
            'hourly_wage'=> 'nullable|numeric',
            'pay_schedule' => 'nullable|string|max:60',
            'w4_filing_status' => 'nullable|string|max:60',
            'insurance_coverage' => 'nullable|string|max:120',
            'caregiver_type' => 'nullable|string|max:50',
            'champs_provider_id' => 'nullable|string|max:60',
            'champs_status' => 'nullable|string|max:120',
            'milogin_user_id' => 'nullable|string|max:60',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'emergency_contact_email' => 'nullable|email|max:255',
            'services' => 'nullable|array',
        ]);

        $original = $caregiver->getOriginal();
        $caregiver->update($validated);

        // Audit any wage change explicitly (mirrors the mockup)
        if (array_key_exists('hourly_wage', $validated) && (float) $original['hourly_wage'] !== (float) $caregiver->hourly_wage) {
            $this->audit($caregiver, 'Field edited', 'Pay & Payroll › Hourly wage',
                '$' . number_format((float) $original['hourly_wage'], 2) . ' / hr',
                '$' . number_format((float) $caregiver->hourly_wage, 2) . ' / hr', null, 'App (web)');
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'caregiver' => $caregiver]);
        }

        return redirect()->back()->with('success', 'Caregiver profile updated.');
    }

    /**
     * Add a second client assignment (Client Assignments tab).
     */
    public function storeAssignment(Request $request, $id)
    {
        $caregiver = $this->findCaregiver($id);

        $data = $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'relationship'    => 'nullable|string|max:100',
            'scheduled_hours' => 'nullable|numeric',
            'live_in'         => 'nullable',
        ]);

        $liveIn = $request->boolean('live_in');
        CaregiverAssignment::create([
            'organization_id' => $caregiver->organization_id,
            'employee_id'     => $caregiver->id,
            'client_id'       => $data['client_id'],
            'relationship'    => $data['relationship'] ?? null,
            'scheduled_hours' => $data['scheduled_hours'] ?? null,
            'live_in'         => $liveIn,
            'evv_status'      => $liveIn ? 'Exempt (live-in)' : 'Active',
            'status'          => 'Active',
            'assigned_since'  => now(),
        ]);
        $caregiver->clients()->syncWithoutDetaching([$data['client_id']]);

        $this->audit($caregiver, 'Assignment added', 'Client Assignments', null,
            optional(Client::find($data['client_id']))->first_name, null, 'App (web)');

        return redirect()->route('caregivers.show', $caregiver->id)->with('success', 'Client assignment added.');
    }

    /**
     * Payroll P4 — toggle the manual "Set up in payroll portal ✓" checkoff.
     * An AI agent or staff flips this after creating the caregiver in the
     * external payroll portal (filing status + direct deposit). Stamps who + when.
     */
    public function markPayrollPortalSetup(Request $request, $id)
    {
        $caregiver = $this->findCaregiver($id);

        if ($request->boolean('undo')) {
            $caregiver->payroll_portal_setup_at = null;
            $caregiver->payroll_portal_setup_by = null;
            $caregiver->save();

            $this->audit($caregiver, 'Field edited', 'Pay & Payroll › Payroll portal setup',
                'Set up', 'Not set up', 'Payroll-portal setup checkoff cleared', 'App (web)');

            return redirect()->back()->with('success', 'Payroll-portal setup checkoff cleared.');
        }

        $caregiver->payroll_portal_setup_at = now();
        $caregiver->payroll_portal_setup_by = $request->user()->id;
        $caregiver->save();

        $this->audit($caregiver, 'Field edited', 'Pay & Payroll › Payroll portal setup',
            'Not set up', 'Set up', 'Marked set up in external payroll portal (filing status + direct deposit)', 'App (web)');

        return redirect()->back()->with('success', 'Marked as set up in the payroll portal.');
    }

    /**
     * Add a note (Notes & Activity tab).
     */
    public function storeNote(Request $request, $id)
    {
        $caregiver = $this->findCaregiver($id);

        $data = $request->validate([
            'body' => 'required|string',
            'tag'  => 'nullable|string|max:40',
        ]);

        CaregiverNote::create([
            'organization_id' => $caregiver->organization_id,
            'employee_id'     => $caregiver->id,
            'author_name'     => trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')) ?: 'Staff',
            'author_role'     => auth()->user()->role ?? 'Staff',
            'author_type'     => 'human',
            'tag'             => $data['tag'] ?? 'General',
            'body'            => $data['body'],
            'noted_at'        => now(),
        ]);

        return redirect()->route('caregivers.show', $caregiver->id)->with('success', 'Note added.');
    }

    public function export()
    {
        $this->authorize('viewAny', Employee::class);

        return $this->registryExport->exportCaregivers();
    }

    public function exportAudit($id)
    {
        $caregiver = $this->findCaregiver($id);
        $this->authorize('view', $caregiver);

        return $this->registryExport->exportCaregiverAudit($caregiver);
    }

    public function exportAuditPdf($id)
    {
        $caregiver = $this->findCaregiver($id);
        $this->authorize('view', $caregiver);

        return $this->registryExport->exportCaregiverAuditPdf($caregiver);
    }

    private function audit($caregiver, $action, $entity, $before = null, $after = null, $detail = null, $source = 'App (web)')
    {
        CaregiverAuditLog::create([
            'organization_id' => $caregiver->organization_id,
            'employee_id'     => $caregiver->id,
            'actor_name'      => trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')) ?: 'System',
            'actor_role'      => auth()->user()->role ?? 'Owner',
            'actor_type'      => 'human',
            'action'          => $action,
            'entity'          => $entity,
            'value_before'    => $before,
            'value_after'     => $after,
            'detail'          => $detail,
            'source'          => $source,
            'occurred_at'     => now(),
        ]);
    }

    /**
     * Resolve a caregiver by id without the session location filter (same pattern as ClientController).
     * Workflow-queue and cross-location links must still open the profile.
     */
    private function findCaregiver(int|string $id, array $with = []): Employee
    {
        $query = Employee::withoutGlobalScopes()
            ->where('position', 'Caregiver');

        if ($with !== []) {
            $query->with($with);
        }

        $caregiver = $query->findOrFail($id);

        $this->authorize('viewCaregiver', $caregiver);

        return $caregiver;
    }
}
