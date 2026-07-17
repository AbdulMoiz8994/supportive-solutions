<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\SendClientRequestRequest;
use App\Http\Requests\Client\StoreClientCareDetailRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientCareDetailRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use App\Models\RequestTemplate;
use App\Services\ClientRequestDeliveryService;
use App\Services\ClientShowDataService;
use App\Services\ClientAuthorizationExportService;
use App\Services\ClientDocumentsExportService;
use App\Services\Communication\CommunicationProfileService;
use App\Services\Communication\WellnessCallService;
use App\Support\ClientRegistryStatus;
use App\Support\TabbedPageTitle;
use App\Services\RegistryExportService;
use App\Services\RegistryMetricsService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        protected ClientShowDataService $clientShowDataService,
        protected ClientRequestDeliveryService $clientRequestDeliveryService,
        protected CommunicationProfileService $communicationProfileService,
        protected RegistryMetricsService $registryMetrics,
        protected RegistryExportService $registryExport,
        protected ClientAuthorizationExportService $authorizationExport,
        protected ClientDocumentsExportService $documentsExport,
    ) {}

    public function create()
    {
        $this->authorize('create', Client::class);

        $coverageTypes = \App\Models\CoverageType::all();
        $pcpContacts   = \App\Models\Contact::where('type', 'Primary Care Physician')->where('is_active', true)->orderBy('name')->get();
        $mcoOptions    = \App\Support\DirectoryMcoOptions::list();

        return view('pages.clients.create', compact('coverageTypes', 'pcpContacts', 'mcoOptions'), ['title' => 'Enrol Client']);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Client::class);

        $clients = $this->registryMetrics->clients();

        $coverageTypes = \App\Models\CoverageType::all();
        $clientStatuses = \App\Models\Status::where('entity_type', 'Client')->get();

        // Display-ready rows for the registry table (search / filter / paginate client-side).
        $rows = $clients->map(function (Client $c) {
            $auth = $c->authStatus();
            $coordinator = $c->caseCoordinator();
            $caregiver = $c->primary_caregiver;
            $statusName = $c->statusRecord?->name ?? $c->status ?? 'Active';

            return [
                'id' => $c->id,
                'name' => trim($c->first_name.' '.$c->last_name),
                'initials' => strtoupper(mb_substr($c->first_name ?? '', 0, 1).mb_substr($c->last_name ?? '', 0, 1)),
                'dob' => $c->dob ? \Carbon\Carbon::parse($c->dob)->format('m/d/Y') : null,
                'age' => $c->age,
                'county' => $c->county,
                'medicaid' => $c->member_id,
                'program' => $c->program_label,
                'program_display' => $c->program_display,
                'status_key' => ClientRegistryStatus::normalize($statusName),
                'mco' => $c->mco_name ?? $coordinator?->name,
                'auth_label' => $auth['label'],
                'auth_tone' => $auth['tone'],
                'caregiver' => $caregiver ? trim($caregiver->first_name.' '.$caregiver->last_name) : null,
                'status' => $statusName,
                'status_tone' => $c->status_tone,
            ];
        })->values();

        // Header KPI stats computed from the same normalized rows shown in the table.
        $stats = $this->registryMetrics->clientStats($clients);
        $tabCounts = $this->registryMetrics->clientTabCounts($rows);

        return view('pages.clients.index', compact('clients', 'rows', 'stats', 'tabCounts', 'coverageTypes', 'clientStatuses'), ['title' => 'Client Registry']);
    }

    public function store(StoreClientRequest $request)
    {
        $validated = $request->validated();

        $statusId = $validated['status_id'] ?? null;
        $status = $statusId ? \App\Models\Status::find($statusId) : \App\Models\Status::where('entity_type', 'Client')->where('name', 'Active')->first();

        $location = session('selected_location', 'Michigan');
        if ($location === 'Company Wide') {
            $location = 'Michigan';
        }

        // SSN: store only the digits, derive last 4, keep full value encrypted
        if (! empty($validated['ssn'])) {
            $ssnDigits = preg_replace('/\D/', '', $validated['ssn']);
            $validated['ssn_last4']    = strlen($ssnDigits) >= 4 ? substr($ssnDigits, -4) : $ssnDigits;
            $validated['ssn_encrypted'] = $ssnDigits;
        }
        unset($validated['ssn']);

        $client = Client::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id ?? \App\Models\Organization::first()?->id ?? 1,
            'status' => $status?->name ?? 'Active',
            'status_id' => $status?->id,
            'office_location' => $location,
            'location_id' => session('selected_location_id'),
        ]));

        return redirect()->route('clients.show', $client->id)->with('success', 'Client enrolled successfully.');
    }

    public function revealSsn($id)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('view', $client);

        if (! $client->ssn_encrypted) {
            return response()->json(['ssn' => null]);
        }

        $formatted = preg_replace('/(\d{3})(\d{2})(\d{4})/', '$1-$2-$3', $client->ssn_encrypted);

        return response()->json(['ssn' => $formatted]);
    }

    public function updateOnboardingStep(Request $request, $id)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $client);

        $validated = $request->validate([
            'step_index' => 'required|integer|min:0|max:10',
            'status'     => 'required|in:pending,in_progress,complete',
            'note'       => 'nullable|string|max:255',
            'date'       => 'nullable|date',
        ]);

        $steps = $client->onboarding_steps ?? [];
        $steps[$validated['step_index']] = [
            'status' => $validated['status'],
            'note'   => $validated['note'] ?? null,
            'date'   => $validated['date'] ?? now()->toDateString(),
        ];
        $client->update(['onboarding_steps' => $steps]);

        return response()->json(['success' => true, 'steps' => $steps]);
    }

    public function show($id)
    {
        $client = Client::withoutGlobalScopes()->with([
            'statusRecord',
            'coverageType',
            'organization',
            'contacts',
            'employees',
            'caregiverAssignments',
            'careDetails',
            'documents',
            'schedules.employee',
            'billings',
            'requests.coordinator',
            'statusHistories',
        ])->findOrFail($id);

        $this->authorize('view', $client);

        $coordinators = \App\Models\Contact::where('type', 'Case Coordinator')->orderBy('name')->get();

        // Directory-driven pickers (client review B1/B2/B5): ASW workers and
        // MCO plans come from the Directory instead of free-text fields.
        $aswContacts = \App\Models\Contact::where('type', \App\Models\Contact::TYPE_AGENCY_STAFF)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'county']);
        $mcoOptions = \App\Support\DirectoryMcoOptions::list();
        $requestHistory = $client->requests;
        $requestTemplates = RequestTemplate::query()
            ->where('organization_id', $client->organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $canSendRequest = auth()->user()->can('sendRequest', $client);
        $clientShow = $this->clientShowDataService->build($client);
        $coverageTypes = \App\Models\CoverageType::all();
        $clientStatuses = \App\Models\Status::where('entity_type', 'Client')
            ->whereIn('name', ['Pending', 'Active', 'On Hold', 'Recovery', 'Discharged', 'Deceased', 'Denied'])
            ->get();

        $currentStatusName = $client->statusRecord?->name ?? $client->status ?? 'Active';
        $statusSince = $client->statusHistories
            ->where('to_status', $currentStatusName)
            ->first()
            ?->effective_date
            ?? $client->created_at;

        // PCP contacts from directory — used for auto-populate in demographics tab
        $pcpContacts = \App\Models\Contact::where('type', 'Primary Care Physician')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'fax', 'provider_id', 'clinic_name']);
        $pcpContactsJson = $pcpContacts->keyBy('id')->toJson();
        $clientCommunications = $this->communicationProfileService->presentersForClient($client);
        $documentChecklist = app(\App\Services\DocumentChecklistService::class)->forClient($client);

        return view('pages.clients.show', compact(
            'client', 'coordinators', 'requestHistory', 'requestTemplates',
            'canSendRequest', 'clientShow', 'coverageTypes', 'pcpContacts', 'pcpContactsJson',
            'clientStatuses', 'statusSince', 'clientCommunications', 'documentChecklist',
            'aswContacts', 'mcoOptions'
        ), ['title' => TabbedPageTitle::client(
            trim($client->first_name.' '.$client->last_name),
            request('tab'),
        )]);
    }

    public function storeCareDetail(StoreClientCareDetailRequest $request, $id)
    {
        $validated = $request->validated();
        $client = Client::withoutGlobalScopes()->findOrFail($id);

        $hoursPerWeek = $validated['total_units'] / 4;

        $client->careDetails()->create(array_merge($validated, [
            'hours_per_week' => $hoursPerWeek,
            'status' => 'Active',
            'organization_id' => $client->organization_id,
        ]));

        return redirect()->back()->with('success', 'Care Detail / Prior Authorization added successfully.');
    }

    /**
     * Persist an inline edit to an existing authorization (care detail).
     * Wired to the "Authorization Details" panel on the client's
     * Program & Authorization tab so hand edits actually commit (and the
     * derived hours/units recompute on reload).
     */
    public function updateCareDetail(UpdateClientCareDetailRequest $request, $id, $careDetail)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $detail = $client->careDetails()->findOrFail($careDetail);

        $validated = $request->validated();

        // Keep the derived weekly hours in step with the authorized units
        // (T1019 is billed in 15-min units → units / 4 = hours/week). This is
        // what makes "Weekly average" and "Units remaining" recompute on reload.
        $detail->update([
            'billing_code'  => $validated['billing_code'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'] ?? null,
            'total_units'   => $validated['total_units'],
            'hours_per_week' => $validated['total_units'] / 4,
            'authorized_by' => $validated['authorized_by'] ?? $detail->authorized_by,
        ]);

        return redirect()
            ->route('clients.show', ['id' => $client->id, 'tab' => $request->input('tab', 'authorization')])
            ->with('success', 'Changes saved.');
    }

    public function update(UpdateClientRequest $request, $id)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $validated = $request->validated();

        if (isset($validated['status_id'])) {
            $status = \App\Models\Status::find($validated['status_id']);
            $validated['status'] = $status?->name;
        }

        // SSN update: derive last 4 and store full value encrypted
        if (! empty($validated['ssn'])) {
            $ssnDigits = preg_replace('/\D/', '', $validated['ssn']);
            $validated['ssn_last4']     = strlen($ssnDigits) >= 4 ? substr($ssnDigits, -4) : $ssnDigits;
            $validated['ssn_encrypted'] = $ssnDigits;
        }
        unset($validated['ssn']);

        // Emergency contact is stored in the contacts relation, not a direct column.
        $emergencyName         = $validated['emergency_name'] ?? null;
        $emergencyRelationship = $validated['emergency_relationship'] ?? null;
        $emergencyPhone        = $validated['emergency_phone'] ?? null;
        $emergencyEmail        = $validated['emergency_email'] ?? null;
        unset($validated['emergency_name'], $validated['emergency_relationship'], $validated['emergency_phone'], $validated['emergency_email']);

        // PCP fields are relational — strip from direct column update.
        $pcpContactId = $validated['pcp_contact_id'] ?? null;
        unset($validated['pcp_contact_id'], $validated['pcp_phone'], $validated['pcp_fax'], $validated['pcp_npi']);

        // Coordinator / ASW pickers persist to the contacts pivot, not columns.
        $coordinatorContactId = $validated['coordinator_contact_id'] ?? null;
        $aswContactId = $validated['asw_contact_id'] ?? null;
        unset($validated['coordinator_contact_id'], $validated['asw_contact_id']);

        $client->update($validated);

        // Persist emergency contact (upsert via contacts pivot).
        if ($emergencyName) {
            $client->load('contacts');
            $emergency = $client->contacts->first(fn ($c) => str_contains(strtolower($c->pivot->role ?? ''), 'emergency'));
            $contactData = [
                'name'      => $emergencyName,
                'phone'     => $emergencyPhone,
                'email'     => $emergencyEmail,
                'type'      => \App\Models\Contact::TYPE_FAMILY_EMERGENCY,
                'is_active' => true,
            ];
            if ($emergency) {
                $emergency->update($contactData);
                if ($emergencyRelationship) {
                    $client->contacts()->updateExistingPivot($emergency->id, ['role' => 'emergency · '.$emergencyRelationship]);
                }
            } else {
                $contact = \App\Models\Contact::create($contactData);
                $client->contacts()->attach($contact->id, ['role' => 'emergency · '.($emergencyRelationship ?? '')]);
            }
        }

        // Attach new PCP selection when changed.
        if ($pcpContactId) {
            $existing = $client->contacts->firstWhere('type', 'Primary Care Physician');
            if ($existing) {
                $client->contacts()->detach($existing->id);
            }
            $client->contacts()->attach($pcpContactId, ['role' => 'Primary Care Physician']);
        }

        // Case coordinator picker (B5) — one coordinator link per client.
        if ($coordinatorContactId) {
            $this->replaceContactRole($client, (int) $coordinatorContactId, 'Case Coordinator', fn ($c) => str_contains(strtolower($c->pivot->role ?? ''), 'coordinator') || $c->type === \App\Models\Contact::TYPE_CASE_COORDINATOR);
        }

        // DHS ASW picker (B1) — the linked worker receives Home Help invoices.
        if ($aswContactId) {
            $this->replaceContactRole($client, (int) $aswContactId, 'ASW · Adult Services Worker', fn ($c) => str_contains(strtolower($c->pivot->role ?? ''), 'asw'));
        }

        // Inline section saves return to the same profile tab; a bare update
        // (no tab supplied) falls back to the registry.
        if ($request->filled('tab')) {
            return redirect()
                ->route('clients.show', ['id' => $client->id, 'tab' => $request->input('tab')])
                ->with('success', 'Changes saved.');
        }

        return redirect()->route('clients.index')->with('success', 'Client record updated successfully.');
    }

    /** Swap the single contact holding a pivot role (coordinator, ASW) for a new pick. */
    protected function replaceContactRole(Client $client, int $contactId, string $role, \Closure $matches): void
    {
        $client->load('contacts');

        foreach ($client->contacts->filter($matches) as $existing) {
            $client->contacts()->detach($existing->id);
        }

        $client->contacts()->attach($contactId, ['role' => $role]);
    }

    public function assignCaregiver(\Illuminate\Http\Request $request, $id, \App\Services\CaregiverAssignmentService $assignmentService)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $client);

        $validated = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'relationship' => 'nullable|string|max:100',
            'live_in'      => 'nullable',
        ]);

        $liveIn    = $request->boolean('live_in');
        $caregiver = \App\Models\Employee::findOrFail($validated['employee_id']);

        $assignmentService->assignToClient(
            $client,
            (int) $validated['employee_id'],
            $validated['relationship'] ?? null,
            $liveIn,
        );

        return redirect()
            ->route('clients.show', ['id' => $client->id, 'tab' => 'caregiver'])
            ->with('success', 'Caregiver assigned: '.$caregiver->first_name.' '.$caregiver->last_name.'.');
    }

    /**
     * Persist an inline edit to the caregiver's assignment (relationship / status)
     * from the client's Caregiver Assignment tab.
     */
    public function updateAssignment(Request $request, $id, $assignment)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $client);

        $record = $client->caregiverAssignments()->findOrFail($assignment);

        $validated = $request->validate([
            'relationship' => ['nullable', 'string', 'max:50'],
            'status'       => ['nullable', 'string', 'in:Active,On Hold,Ended'],
        ]);

        $record->update(array_filter($validated, fn ($v) => $v !== null));

        return redirect()
            ->route('clients.show', ['id' => $client->id, 'tab' => 'caregiver'])
            ->with('success', 'Changes saved.');
    }

    public function changeStatus(Request $request, $id)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $client);

        $validated = $request->validate([
            'to_status'         => 'required|string|in:Pending,Active,On Hold,Recovery,Discharged,Deceased,Denied',
            'effective_date'    => 'required|date',
            'last_service_date' => 'nullable|date',
            'reason'            => 'nullable|string|max:255',
            'note'              => 'nullable|string|max:2000',
        ]);

        $fromStatus = $client->statusRecord?->name ?? $client->status ?? 'Active';
        $newStatus = \App\Models\Status::where('entity_type', 'Client')
            ->where('name', $validated['to_status'])
            ->first();

        \App\Models\ClientStatusHistory::create([
            'client_id'         => $client->id,
            'from_status'       => $fromStatus,
            'to_status'         => $validated['to_status'],
            'effective_date'    => $validated['effective_date'],
            'last_service_date' => $validated['last_service_date'] ?? null,
            'reason'            => $validated['reason'] ?? null,
            'note'              => $validated['note'] ?? null,
            'changed_by'        => auth()->id(),
            'changed_by_name'   => auth()->user()->name ?? null,
        ]);

        $client->update([
            'status'    => $validated['to_status'],
            'status_id' => $newStatus?->id,
        ]);

        return redirect()
            ->route('clients.show', $client->id)
            ->with('success', "Status changed from {$fromStatus} to {$validated['to_status']}.");
    }

    public function destroy($id)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('delete', $client);
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client record removed.');
    }

    public function storeRequest(SendClientRequestRequest $request, $id)
    {
        $client = Client::withoutGlobalScopes()->with(['contacts', 'organization'])->findOrFail($id);
        $template = RequestTemplate::withoutGlobalScopes()->findOrFail($request->validated('request_template_id'));

        try {
            $clientRequest = $this->clientRequestDeliveryService->send(
                $client,
                $template,
                $request->user(),
                $request->validated()
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if ($request->ajax() || $request->wantsJson()) {
                throw $exception;
            }

            return redirect()->back()
                ->withInput()
                ->withErrors($exception->errors())
                ->with('error', 'Unable to send request. Please check recipient details.');
        }

        $message = match ($clientRequest->status) {
            \App\Models\ClientRequest::STATUS_SENT => 'Request sent and logged successfully.',
            \App\Models\ClientRequest::STATUS_FAILED => 'Request logged with delivery failure. Review the audit history for details.',
            default => 'Request logged for manual follow-up.',
        };

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function export()
    {
        $this->authorize('viewAny', Client::class);

        return $this->registryExport->exportClients();
    }

    public function downloadPaLetter($id)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('view', $client);

        return $this->authorizationExport->downloadPaLetter($client);
    }

    public function exportAuthorizations($id)
    {
        $client = Client::withoutGlobalScopes()->with('careDetails')->findOrFail($id);
        $this->authorize('view', $client);

        return $this->authorizationExport->exportAuthorizations($client);
    }

    public function downloadAllDocuments($id)
    {
        $client = Client::withoutGlobalScopes()->with('documents')->findOrFail($id);
        $this->authorize('view', $client);

        return $this->documentsExport->downloadAll($client);
    }

    public function triggerWellnessCall($id, WellnessCallService $wellness)
    {
        $client = Client::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $client);

        $result = $wellness->placeCallForClient($client, force: true);

        return back()->with(
            $result['success'] ? 'success' : 'warning',
            $result['message'],
        );
    }
}
