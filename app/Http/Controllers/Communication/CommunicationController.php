<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreManualCommunicationRequest;
use App\Models\Client;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use App\Models\Employee;
use App\Services\Communication\CommunicationDashboardService;
use App\Services\Communication\CommunicationInboundService;
use App\Services\Communication\CommunicationIntegrationStatusService;
use App\Services\Communication\CommunicationWorkflowQueueService;
use App\Support\CommunicationOrganizationResolver;
use App\Support\CommunicationPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function __construct(
        protected CommunicationDashboardService $dashboard,
        protected CommunicationIntegrationStatusService $integrationStatus,
        protected CommunicationWorkflowQueueService $workflowQueue,
        protected CommunicationInboundService $inbound,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Communication::class);

        $periodFilter = $request->string('period')->toString() ?: null;
        $period = $this->dashboard->resolvePeriod(
            in_array($periodFilter, ['today', 'this_week'], true) ? null : ($periodFilter ?: now()->format('Y-m'))
        );

        $query = $this->dashboard->baseQuery($period, $periodFilter)
            ->with(['sender', 'related', 'template']);

        $this->dashboard->applyTabFilter($query, $request->string('tab')->toString() ?: null);
        $this->dashboard->applyPartyFilter($query, $request->string('party')->toString() ?: null);

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $sort = $request->string('sort')->toString() ?: 'newest';
        if ($sort === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        $communications = $query->paginate(25)->withQueryString();
        $summary = $this->dashboard->summary($period, $periodFilter);
        $channelCounts = $this->dashboard->channelCounts($period, $periodFilter);
        $integrationStatus = $this->integrationStatus->forCompose();
        $composeTemplates = CommunicationTemplate::query()
            ->where('is_active', true)
            ->whereIn('channel', [CommunicationTemplate::CHANNEL_EMAIL, CommunicationTemplate::CHANNEL_SMS])
            ->orderBy('name')
            ->get(['id', 'name', 'channel', 'subject', 'body']);

        return view('pages.communications.index', [
            'title' => 'Communications',
            'communications' => $communications,
            'presenters' => $communications->getCollection()->map(fn ($c) => CommunicationPresenter::make($c)),
            'summary' => $summary,
            'channelCounts' => $channelCounts,
            'period' => $period,
            'periodFilter' => $periodFilter,
            'periodOptions' => $this->dashboard->periodOptions($period),
            'prevPeriod' => $period->copy()->subMonth(),
            'nextPeriod' => $period->copy()->addMonth(),
            'filters' => $request->only(['tab', 'party', 'search', 'sort', 'period']),
            'integrationStatus' => $integrationStatus,
            'composeTemplates' => $composeTemplates,
        ]);
    }

    public function show(Communication $communication): View
    {
        $this->authorize('view', $communication);

        $communication->load(['sender', 'related', 'template', 'attachments']);
        $presenter = CommunicationPresenter::make($communication);

        return view('pages.communications.show', [
            'title' => $presenter->partyName().' — '.$presenter->channelLabel(),
            'communication' => $communication,
            'presenter' => $presenter,
            'canViewBody' => auth()->user()->can('view', $communication),
            'periodLabel' => ($communication->sent_at ?? $communication->created_at)?->format('M Y'),
        ]);
    }

    /**
     * Needs-reply workflow: mark an inbound item as handled so it leaves the
     * "Needs review" queue (dashboard chips, filters and KPIs update live).
     */
    public function markHandled(Request $request, Communication $communication)
    {
        $this->authorize('update', $communication);

        $metadata = $communication->metadata ?? [];
        $metadata['handled_by'] = 'staff';
        $metadata['handled_by_name'] = $request->user()->name;
        $metadata['handled_at'] = now()->toIso8601String();

        $communication->update([
            'metadata' => $metadata,
            'status' => $communication->status === Communication::STATUS_RECEIVED
                ? Communication::STATUS_READ
                : $communication->status,
        ]);

        $this->workflowQueue->resolveItem($communication);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Marked as handled — removed from the needs-reply queue.');
    }

    public function storeManual(StoreManualCommunicationRequest $request)
    {
        $related = null;
        if ($request->filled('related_type') && $request->filled('related_id')) {
            $related = $request->input('related_type') === 'Employee'
                ? Employee::findOrFail($request->integer('related_id'))
                : Client::findOrFail($request->integer('related_id'));
        }

        $direction = $request->input('direction', Communication::DIRECTION_OUTBOUND);

        $communication = Communication::create([
            'organization_id' => CommunicationOrganizationResolver::resolve(
                $request->user(),
                $related instanceof Client ? $related : null,
                null,
                $related instanceof Employee ? $related : null,
            ),
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->id,
            'channel' => $request->string('channel'),
            'direction' => $direction,
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
            'status' => Communication::STATUS_RECEIVED,
            'sender_id' => auth()->id(),
            'metadata' => $request->input('metadata', []),
            'sent_at' => now(),
        ]);

        // Run AI triage and workflow queue for manually logged inbound entries.
        if ($direction === Communication::DIRECTION_INBOUND) {
            $this->inbound->applyInboundTriage($communication);
        }

        return redirect()
            ->route('communications.show', $communication)
            ->with('success', ucfirst($communication->channel).' logged successfully.');
    }
}
