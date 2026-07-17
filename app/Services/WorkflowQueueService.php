<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillingClaimAudit;
use App\Models\CareDetail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Task;
use App\Models\WorkflowQueueItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WorkflowQueueService
{
    public function __construct(
        protected GlobalSettingsService $globalSettings,
        protected ApprovalQueueMetricsService $metrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pageData(?int $orgId, Request $request): array
    {
        $today = Carbon::now()->startOfDay();
        $sort = $this->normalizeSort($request->query('sort', 'sla'));
        $filter = $this->normalizeFilter($request->query('filter'));
        $snapshot = $this->queueSnapshot($orgId, $today);
        $filtered = $this->filterAndSortApprovalCards($snapshot['approvals'] ?? [], $sort, $filter);
        $paged = $this->paginateApprovalCards($filtered);

        return array_merge($snapshot, $paged, [
            'title' => 'Workflow Queues',
            'missRateThreshold' => $this->missRateThreshold(),
            'sort' => $sort,
            'filter' => $filter,
            'sortOptions' => $this->sortOptions(),
            'filterOptions' => $this->filterOptions($snapshot['approvals'] ?? []),
            'sectionApprovalsLabel' => $this->approvalsSectionLabel(
                $snapshot['approvalCount'] ?? 0,
                count($filtered),
                $filter,
            ),
        ]);
    }

    /**
     * Filtered/sorted approval cards ready for pagination (Load more / initial page).
     *
     * @return array{approvals: list<array<string, mixed>>, approvalsMeta: array{total: int, offset: int, limit: int, loaded: int, hasMore: bool}, sectionApprovalsLabel: string, sort: string, filter: string|null}
     */
    public function approvalsPage(?int $orgId, string $sort = 'sla', ?string $filter = null, int $offset = 0, ?int $limit = null, ?Carbon $today = null): array
    {
        $today = $today ?? Carbon::now()->startOfDay();
        $sort = $this->normalizeSort($sort);
        $filter = $this->normalizeFilter($filter);
        $all = $this->approvalCards($orgId, $today);
        $filtered = $this->filterAndSortApprovalCards($all, $sort, $filter);
        $paged = $this->paginateApprovalCards($filtered, $offset, $limit);

        return array_merge($paged, [
            'sort' => $sort,
            'filter' => $filter,
            'sectionApprovalsLabel' => $this->approvalsSectionLabel(
                $this->approvalCount($orgId),
                count($filtered),
                $filter,
            ),
        ]);
    }

    /**
     * Live counts and KPI strip — used by the page and AJAX refresh after approve/reject.
     *
     * @return array<string, mixed>
     */
    public function queueSnapshot(?int $orgId, ?Carbon $today = null, bool $includeLists = true): array
    {
        $today = $today ?? Carbon::now()->startOfDay();
        $approvals = $this->approvalCards($orgId, $today);
        $humanTasks = $this->humanTasks($orgId);
        $exceptions = $this->exceptions($orgId);

        // Always match Dashboard / Staff / sidebar — never inflate with demo cards.
        $pendingApprovals = $this->approvalCount($orgId);
        $humanCount = count($humanTasks);
        $exceptionCount = count($exceptions);
        $slaAtRisk = collect($approvals)->whereIn('sla.tone', ['now', 'soon'])->count();
        $dueTodayApprovals = collect($approvals)->where('sla.tone', 'now')->count();
        $dueTodayTasks = collect($humanTasks)->where('due_tone', 'urgent')->count();
        $missRate = $this->weeklyAgentMissRate();
        $approvalsMeta = $this->approvalsMeta(count($approvals));

        $payload = [
            'approvalCount' => $pendingApprovals,
            'humanTaskCount' => $humanCount,
            'exceptionCount' => $exceptionCount,
            'approvalsMeta' => $approvalsMeta,
            'kpis' => [
                ['label' => 'Pending approvals', 'value' => (string) $pendingApprovals, 'sub' => $dueTodayApprovals.' due today', 'tone' => $pendingApprovals > 0 ? 'alert' : 'default'],
                ['label' => 'Human tasks', 'value' => (string) $humanCount, 'sub' => $dueTodayTasks ? $dueTodayTasks.' due today' : '—', 'tone' => 'default'],
                ['label' => 'SLA at risk', 'value' => (string) $slaAtRisk, 'sub' => '< 8 hrs left', 'tone' => $slaAtRisk > 0 ? 'alert' : 'default'],
                ['label' => 'Agent miss-rate (wk)', 'value' => $missRate.'%', 'sub' => 'under '.$this->missRateThreshold().'% threshold · '.$exceptionCount.' flagged', 'tone' => 'ok'],
            ],
            'subtitle' => "{$pendingApprovals} awaiting your approval · {$humanCount} human tasks · {$exceptionCount} exception".($exceptionCount === 1 ? '' : 's').' flagged',
            'sectionApprovalsLabel' => $pendingApprovals.' items · 24-hr SLA',
            'sectionHumanLabel' => $humanCount.' items',
            'sectionExceptionsLabel' => $exceptionCount.' flagged',
        ];

        if ($includeLists) {
            $payload['approvals'] = $approvals;
            $payload['humanTasks'] = $humanTasks;
            $payload['exceptions'] = $exceptions;
        }

        return $payload;
    }

    /**
     * Slice sorted approval cards for the Owner Approval Queue list.
     *
     * @param  list<array<string, mixed>>  $cards
     * @return array{approvals: list<array<string, mixed>>, approvalsMeta: array{total: int, offset: int, limit: int, loaded: int, hasMore: bool}}
     */
    public function paginateApprovalCards(array $cards, int $offset = 0, ?int $limit = null): array
    {
        $limit = max(1, $limit ?? (int) config('workflow_queues.approvals_per_page', 12));
        $offset = max(0, $offset);
        $total = count($cards);
        $slice = array_values(array_slice($cards, $offset, $limit));
        $loaded = min($offset + count($slice), $total);

        return [
            'approvals' => $slice,
            'approvalsMeta' => [
                'total' => $total,
                'offset' => $offset,
                'limit' => $limit,
                'loaded' => $loaded,
                'hasMore' => $loaded < $total,
            ],
        ];
    }

    /**
     * @return array{total: int, offset: int, limit: int, loaded: int, hasMore: bool}
     */
    public function approvalsMeta(int $total, int $loaded = 0, ?int $limit = null): array
    {
        $limit = max(1, $limit ?? (int) config('workflow_queues.approvals_per_page', 12));
        $total = max(0, $total);
        $loaded = max(0, min($loaded, $total));

        return [
            'total' => $total,
            'offset' => 0,
            'limit' => $limit,
            'loaded' => $loaded,
            'hasMore' => $loaded < $total,
        ];
    }

    /**
     * @return array{approvals: list<array<string, mixed>>, approvalCount: int, approvalChips: list<array{label: string}>}
     */
    public function approvalPayload(?int $orgId, Carbon $today): array
    {
        $allApprovals = $this->pendingLegacyApprovals($orgId, $today);

        return [
            'approvals' => $this->pendingLegacyApprovals($orgId, $today, applyDisplayLimits: true),
            'approvalCount' => count($allApprovals),
            'approvalChips' => $this->summariseApprovals($allApprovals, $orgId, $today),
        ];
    }

    /**
     * Canonical live approval queue: legacy sources minus items already
     * resolved through the Workflow Queue page. Dashboard, Workflow Queue,
     * sidebar badge and Staff KPIs all derive from this one list so their
     * counts can never drift apart (client review item A3).
     *
     * @return list<array<string, mixed>>
     */
    private function pendingLegacyApprovals(?int $orgId, Carbon $today, bool $applyDisplayLimits = false): array
    {
        $resolvedSlugs = $this->resolvedSlugs($orgId, WorkflowQueueItem::TYPE_APPROVAL);

        return collect($this->buildLegacyApprovalQueue($orgId, $today, $applyDisplayLimits))
            ->reject(fn (array $item) => in_array($item['key'], $resolvedSlugs, true))
            ->values()
            ->all();
    }

    public function approvalCount(?int $orgId): int
    {
        // Same canonical list as approvalPayload() — never count demo cards
        // when the live queue is empty (client review A3).
        return count($this->pendingLegacyApprovals($orgId, Carbon::now()->startOfDay()));
    }

    public function humanTaskCount(?int $orgId): int
    {
        return count($this->humanTasks($orgId));
    }

    public function totalQueueCount(?int $orgId): int
    {
        return $this->approvalCount($orgId) + $this->humanTaskCount($orgId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvalCards(?int $orgId, Carbon $today): array
    {
        $live = collect($this->pendingLegacyApprovals($orgId, $today))
            ->map(fn (array $item) => $this->legacyItemToCard($item, $today))
            ->filter(fn (?array $card) => $card !== null)
            ->map(fn (array $card) => $this->normalizeApprovalCard($card))
            ->values();

        if ($live->isNotEmpty() || ! config('workflow_queues.demo_fallback', true)) {
            return $this->sortApprovalCards($live->all());
        }

        $resolvedSlugs = $this->resolvedSlugs($orgId, WorkflowQueueItem::TYPE_APPROVAL);

        $demo = collect(config('workflow_queues.demo_approvals', []))
            ->reject(fn (array $card) => in_array($card['slug'], $resolvedSlugs, true))
            ->map(fn (array $card) => $this->normalizeApprovalCard($card))
            ->values()
            ->all();

        return $this->sortApprovalCards($demo);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    public function filterAndSortApprovalCards(array $cards, string $sort = 'sla', ?string $filter = null): array
    {
        return $this->sortApprovalCards(
            $this->filterApprovalCards($cards, $filter),
            $this->normalizeSort($sort),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    public function filterApprovalCards(array $cards, ?string $filter): array
    {
        $filter = $this->normalizeFilter($filter);

        if ($filter === null) {
            return array_values($cards);
        }

        return array_values(array_filter($cards, function (array $card) use ($filter) {
            return match ($filter) {
                'due_now' => ($card['sla']['tone'] ?? '') === 'now',
                'due_soon' => ($card['sla']['tone'] ?? '') === 'soon',
                default => ($card['filter_key'] ?? '') === $filter,
            };
        }));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function sortOptions(): array
    {
        return [
            ['value' => 'sla', 'label' => 'SLA · urgent first'],
            ['value' => 'sla_desc', 'label' => 'SLA · urgent last'],
            ['value' => 'title', 'label' => 'Title A–Z'],
            ['value' => 'type', 'label' => 'Type'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array{value: string, label: string}>
     */
    public function filterOptions(array $cards): array
    {
        $options = [
            ['value' => '', 'label' => 'All types'],
        ];

        $tones = collect($cards)->pluck('sla.tone')->filter()->unique();
        if ($tones->contains('now')) {
            $options[] = ['value' => 'due_now', 'label' => 'Due now'];
        }
        if ($tones->contains('soon')) {
            $options[] = ['value' => 'due_soon', 'label' => 'Due soon'];
        }

        $labels = [
            'billing' => 'Billing',
            'pa' => 'Authorizations',
            'background' => 'Background checks',
            'client' => 'Activations',
            'agent' => 'Agent tasks',
            'other' => 'Other',
        ];

        $present = collect($cards)->pluck('filter_key')->filter()->unique();
        foreach ($labels as $value => $label) {
            if ($present->contains($value)) {
                $options[] = ['value' => $value, 'label' => $label];
            }
        }

        return $options;
    }

    public function normalizeSort(mixed $sort): string
    {
        $sort = is_string($sort) ? strtolower(trim($sort)) : 'sla';

        return in_array($sort, ['sla', 'sla_desc', 'title', 'type'], true) ? $sort : 'sla';
    }

    public function normalizeFilter(mixed $filter): ?string
    {
        if (! is_string($filter) || trim($filter) === '') {
            return null;
        }

        $filter = strtolower(trim($filter));
        $allowed = ['due_now', 'due_soon', 'billing', 'pa', 'background', 'client', 'agent', 'other'];

        return in_array($filter, $allowed, true) ? $filter : null;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    private function sortApprovalCards(array $cards, string $sort = 'sla'): array
    {
        if ($sort === 'title') {
            usort($cards, fn (array $a, array $b) => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));

            return array_values($cards);
        }

        if ($sort === 'type') {
            usort($cards, function (array $a, array $b) {
                $left = (string) ($a['category'] ?? $a['kind'] ?? '');
                $right = (string) ($b['category'] ?? $b['kind'] ?? '');
                $cmp = strcasecmp($left, $right);

                return $cmp !== 0
                    ? $cmp
                    : strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });

            return array_values($cards);
        }

        $descending = $sort === 'sla_desc';

        usort($cards, function (array $a, array $b) use ($descending) {
            $aRank = $this->slaSortRank($a);
            $bRank = $this->slaSortRank($b);
            $cmp = $aRank <=> $bRank;

            if ($cmp === 0) {
                $cmp = strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }

            return $descending ? -$cmp : $cmp;
        });

        return array_values($cards);
    }

    /**
     * Lower rank = more urgent (shown first for default SLA sort).
     *
     * @param  array<string, mixed>  $card
     */
    private function slaSortRank(array $card): int
    {
        $tone = (string) ($card['sla']['tone'] ?? 'ok');
        $toneBase = match ($tone) {
            'now' => 0,
            'soon' => 1000,
            default => 2000,
        };

        return $toneBase + $this->slaHoursRemaining($card);
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function slaHoursRemaining(array $card): int
    {
        $label = strtolower((string) ($card['sla']['label'] ?? ''));

        if ($label === '' || str_contains($label, 'today') || str_contains($label, 'overdue') || str_contains($label, 'now')) {
            return 0;
        }

        if (preg_match('/(\d+)\s*h/', $label, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)\s*d/', $label, $matches)) {
            return ((int) $matches[1]) * 24;
        }

        return 50;
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function normalizeApprovalCard(array $card): array
    {
        if (empty($card['kind'])) {
            $card['kind'] = $this->inferKindFromCard($card);
        }

        $card['filter_key'] = $this->filterKeyForKind((string) ($card['kind'] ?? ''));

        return $card;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function inferKindFromCard(array $card): string
    {
        $slug = (string) ($card['slug'] ?? '');
        $category = strtolower((string) ($card['category'] ?? ''));
        $approveType = (string) ($card['approve_type'] ?? '');

        return match (true) {
            str_starts_with($slug, 'billing-'), str_contains($category, 'billing'), str_contains($category, 'pre-billing') => 'Billing hold',
            str_starts_with($slug, 'claim-'), str_contains($category, 'denied') => 'Held claim',
            str_starts_with($slug, 'pa-'), str_contains($category, 'authorization') => 'PA renewal',
            str_starts_with($slug, 'background-'), str_contains($category, 'background'), str_contains($slug, 'oig') => 'Background flag',
            str_starts_with($slug, 'client-'), str_contains($category, 'activate') => 'Client activation',
            str_starts_with($slug, 'task-'), str_contains($category, 'agent') => 'Agent task',
            $approveType === 'billing' => 'Billing hold',
            $approveType === 'pa' => 'PA renewal',
            $approveType === 'background' => 'Background flag',
            $approveType === 'client_activate' => 'Client activation',
            $approveType === 'task' => 'Agent task',
            default => (string) ($card['kind'] ?? 'Other'),
        };
    }

    private function filterKeyForKind(string $kind): string
    {
        return match ($kind) {
            'Billing hold', 'Held claim' => 'billing',
            'PA renewal' => 'pa',
            'Background flag' => 'background',
            'Client activation' => 'client',
            'Agent task' => 'agent',
            default => 'other',
        };
    }

    private function approvalsSectionLabel(int $totalPending, int $filteredCount, ?string $filter): string
    {
        if ($filter !== null) {
            return $filteredCount.' shown · '.$totalPending.' total · 24-hr SLA';
        }

        return $totalPending.' items · 24-hr SLA';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function humanTasks(?int $orgId): array
    {
        $resolvedSlugs = $this->resolvedSlugs($orgId, WorkflowQueueItem::TYPE_HUMAN_TASK);

        $dbTasks = WorkflowQueueItem::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('queue_type', WorkflowQueueItem::TYPE_HUMAN_TASK)
            ->where('status', WorkflowQueueItem::STATUS_PENDING)
            ->orderBy('sla_due_at')
            ->get()
            ->map(fn (WorkflowQueueItem $item) => array_merge([
                'slug' => $item->slug,
                'db_id' => $item->id,
            ], $item->meta ?? []))
            ->all();

        if ($dbTasks !== []) {
            return $dbTasks;
        }

        return collect(config('workflow_queues.demo_human_tasks', []))
            ->reject(fn (array $task) => in_array($task['slug'], $resolvedSlugs, true))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exceptions(?int $orgId): array
    {
        $resolvedSlugs = $this->resolvedSlugs($orgId, WorkflowQueueItem::TYPE_EXCEPTION);

        return collect(config('workflow_queues.demo_exceptions', []))
            ->reject(fn (array $item) => in_array($item['slug'], $resolvedSlugs, true))
            ->values()
            ->all();
    }

    public function applyAction(
        ?int $orgId,
        string $slug,
        string $action,
        ?string $approveType = null,
        ?int $approveId = null,
    ): array {
        if ($approveType && $approveId) {
            return [
                'mode' => 'live',
                'approve_type' => $approveType,
                'approve_id' => $approveId,
                'action' => $action,
            ];
        }

        $status = match ($action) {
            'approve', 'complete' => in_array($slug, collect(config('workflow_queues.demo_human_tasks', []))->pluck('slug')->all(), true)
                ? WorkflowQueueItem::STATUS_COMPLETED
                : WorkflowQueueItem::STATUS_APPROVED,
            'hold' => WorkflowQueueItem::STATUS_HELD,
            'reject', 'dismiss' => WorkflowQueueItem::STATUS_REJECTED,
            default => WorkflowQueueItem::STATUS_DISMISSED,
        };

        $type = str_starts_with($slug, 'demo-task-')
            ? WorkflowQueueItem::TYPE_HUMAN_TASK
            : (str_starts_with($slug, 'demo-fax-') ? WorkflowQueueItem::TYPE_EXCEPTION : WorkflowQueueItem::TYPE_APPROVAL);

        WorkflowQueueItem::updateOrCreate(
            [
                'organization_id' => $orgId,
                'queue_type' => $type,
                'slug' => $slug,
            ],
            [
                'status' => $status,
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
            ],
        );

        return ['mode' => 'demo', 'status' => $status];
    }

    public function actionMessage(string $slug, string $action, ?string $approveType = null, mixed $model = null): string
    {
        if ($approveType && $model) {
            return $this->liveActionMessage($approveType, $action, $model);
        }

        if ($card = $this->findDemoApproval($slug)) {
            return $this->demoApprovalActionMessage($card, $action);
        }

        if ($task = $this->findDemoHumanTask($slug)) {
            return $this->demoHumanTaskActionMessage($task, $action);
        }

        if ($exception = $this->findDemoException($slug)) {
            return $this->demoExceptionActionMessage($exception, $action);
        }

        return $this->genericActionMessage($slug, $action);
    }

    public function liveApproveMessage(string $type, mixed $model): string
    {
        return $this->liveActionMessage($type, 'approve', $model);
    }

    private function liveActionMessage(string $type, string $action, mixed $model): string
    {
        $clientName = $this->modelDisplayName($type, $model);

        return match ($action) {
            'hold' => match ($type) {
                'billing' => "Billing hold kept for {$clientName} — invoice {$model->invoice_number} stays Pending until CP-01 / blocked-claim review is complete.",
                'pa' => "PA renewal for {$clientName} placed on hold — submit the prepared packet before authorization expires.",
                'client_activate' => "Activation for {$clientName} held — client remains on Hold until you approve.",
                'background' => "OIG flag for {$clientName} kept on hold — verify same-person by address/DOB before clearing or disqualifying.",
                'task' => "Agent task \"{$model->title}\" kept pending — still awaiting approval.",
                default => "{$clientName} kept on hold — item remains in your approval queue.",
            },
            'reject' => match ($type) {
                'client_activate' => "Activation rejected for {$clientName} — client stays on Hold; no services will start.",
                'background' => "Match confirmed for {$clientName} — caregiver flagged for disqualification review.",
                'task' => "Agent task \"{$model->title}\" rejected — returned to in progress without completing.",
                default => "{$clientName} rejected — item removed from your approval queue.",
            },
            default => match ($type) {
                'billing' => "Invoice {$model->invoice_number} for {$clientName} approved — status set to Sent and removed from your queue.",
                'pa' => "Prior authorization for {$clientName} renewed — extended 6 months and marked Active.",
                'client_activate' => "{$clientName} activated — client status updated to Active and removed from your queue.",
                'background' => "Background check cleared for {$clientName} — OIG flag dismissed and caregiver can resume service.",
                'task' => "Task \"{$model->title}\" approved and marked Done.",
                default => "{$clientName} approved — item removed from your approval queue.",
            },
        };
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function demoApprovalActionMessage(array $card, string $action): string
    {
        $slug = $card['slug'] ?? '';

        $messages = [
            'demo-oig-mahmoud' => [
                'approve' => 'Mahmoud Ghazawai cleared — OIG flag dismissed as likely false match; caregiver can resume service.',
                'reject' => 'Match confirmed for Mahmoud Ghazawai — caregiver queued for disqualification and replacement.',
                'hold' => 'OIG flag for Mahmoud Ghazawai kept on hold — verify same-person by address/DOB before clearing or disqualifying.',
            ],
            'demo-pa-maria' => [
                'approve' => 'PA renewal for Maria Hassan approved — prepared packet submitted to Aetna via Availity.',
                'hold' => 'PA renewal for Maria Hassan on hold — submit before authorization expires on Jun 14.',
            ],
            'demo-activate-layla' => [
                'approve' => 'Layla Ahmed activated and linked to Khalil Ahmed\'s chart — caregiver onboarding complete.',
                'reject' => 'Caregiver activation for Layla Ahmed rejected — remains pending onboarding review.',
                'hold' => 'Caregiver activation for Layla Ahmed on hold — review CHAMPS and background checks before activating.',
            ],
            'demo-billing-hisham' => [
                'approve' => 'Billing hold for Hisham Khan acknowledged — PA renewal workflow started; do not bill until auth is restored.',
                'hold' => 'Billing hold for Hisham Khan kept — investigate unpaid claim and expired PA before releasing this bill.',
            ],
            'demo-denied-eleanor' => [
                'approve' => 'Resubmission approved for Eleanor Morrison — agent will fax MDHHS-6200 to DHS once the physician form arrives.',
                'hold' => 'DHS denial for Eleanor Morrison on hold — waiting on missing MDHHS-6200 before resubmitting.',
            ],
            'demo-activate-khalil' => [
                'approve' => 'Khalil Ahmed activated — DHS Home Help services can start; chart moved to Active.',
                'reject' => 'Client activation for Khalil Ahmed rejected — chart stays Pending Application.',
                'hold' => 'Activation for Khalil Ahmed on hold — verify determination and caregiver linkage before going live.',
            ],
            'demo-discharge-helen' => [
                'approve' => 'Discharge confirmed for Helen Brooks — services stopped, billing locked, and caregiver assignment closed.',
                'hold' => 'Discharge for Helen Brooks on hold — verify family death report before closing the chart.',
            ],
        ];

        if (isset($messages[$slug][$action])) {
            return $messages[$slug][$action];
        }

        $title = $card['title'] ?? 'Queue item';

        return match ($action) {
            'approve' => "{$title} approved — removed from your approval queue.",
            'hold' => "{$title} placed on hold — it will stay in your queue until you take further action.",
            'reject' => "{$title} rejected — removed from your approval queue.",
            default => "{$title} updated.",
        };
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function demoHumanTaskActionMessage(array $task, string $action): string
    {
        $title = $task['title'] ?? 'Human task';

        return match ($action) {
            'complete' => "Marked complete: {$title}. Removed from human tasks.",
            'hold' => "Task on hold: {$title}. It remains in human tasks until you finish or reassign it.",
            default => "{$title} updated.",
        };
    }

    /**
     * @param  array<string, mixed>  $exception
     */
    private function demoExceptionActionMessage(array $exception, string $action): string
    {
        $title = $exception['title'] ?? 'Exception';

        return match ($action) {
            'approve' => "{$title} acknowledged — review flagged fields before posting to the chart.",
            'dismiss' => "{$title} dismissed — removed from exceptions.",
            default => "{$title} updated.",
        };
    }

    private function genericActionMessage(string $slug, string $action): string
    {
        $label = str_replace('-', ' ', $slug);

        return match ($action) {
            'approve', 'complete' => ucfirst($label).' completed — removed from your queue.',
            'hold' => ucfirst($label).' placed on hold — it remains in your queue.',
            'reject', 'dismiss' => ucfirst($label).' rejected — removed from your queue.',
            default => ucfirst($label).' updated.',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDemoApproval(string $slug): ?array
    {
        return collect(config('workflow_queues.demo_approvals', []))
            ->firstWhere('slug', $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDemoHumanTask(string $slug): ?array
    {
        return collect(config('workflow_queues.demo_human_tasks', []))
            ->firstWhere('slug', $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDemoException(string $slug): ?array
    {
        return collect(config('workflow_queues.demo_exceptions', []))
            ->firstWhere('slug', $slug);
    }

    private function modelDisplayName(string $type, mixed $model): string
    {
        return match ($type) {
            'billing' => $model->client
                ? trim($model->client->first_name.' '.$model->client->last_name)
                : 'client',
            'pa' => $model->client
                ? trim($model->client->first_name.' '.$model->client->last_name)
                : 'client',
            'client_activate' => trim($model->first_name.' '.$model->last_name),
            'background' => trim($model->first_name.' '.$model->last_name),
            'task' => $model->title ?? 'task',
            default => 'item',
        };
    }

    public function missRateThreshold(): float
    {
        return (float) ($this->globalSettings->get('automation.miss_rate_ceiling')
            ?? config('workflow_queues.miss_rate_threshold', 2.0));
    }

    private function weeklyAgentMissRate(): string
    {
        $fleet = config('staff_ai_agents.fleet.fleet_miss_rate_pct', 0.7);

        return number_format((float) $fleet, 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLegacyApprovalQueue(?int $orgId, Carbon $today, bool $applyDisplayLimits = true): array
    {
        $items = [];
        $seenReviewKeys = [];

        $queueItem = function (array $item) use (&$items, &$seenReviewKeys): void {
            $reviewKey = $item['reviewKey'] ?? ($item['kind'].'|'.($item['reviewUrl'] ?? ''));

            if (isset($seenReviewKeys[$reviewKey])) {
                return;
            }

            $seenReviewKeys[$reviewKey] = true;
            $items[] = $item;
        };

        $billingPeriod = null;
        $pendingBillings = $this->metrics->pendingBillingHoldsQuery($orgId, $billingPeriod)
            ->with('client')
            ->latest();

        if ($applyDisplayLimits) {
            $pendingBillings->limit(6);
        }

        $pendingBillings->get()->each(function ($b) use ($queueItem) {
            $name = $b->client ? trim($b->client->first_name.' '.$b->client->last_name) : 'Client';
            $queueItem([
                'key' => 'billing-'.$b->id,
                'kind' => 'Billing hold',
                'title' => "Billing hold — {$name}",
                'subtitle' => "{$b->invoice_number} · ".$this->money($b->total_amount).' ready to send',
                'approveType' => 'billing',
                'approveId' => $b->id,
                'approveUrl' => route('dashboard.approve', ['type' => 'billing', 'id' => $b->id]),
                'reviewUrl' => $this->resolveBillingReviewUrl($b),
                'reviewKey' => 'billing|'.$b->id,
                'canApprove' => true,
            ]);
        });

        $blockedClaims = $this->metrics->heldClaimsForApprovalQueue($orgId, $billingPeriod);

        if ($applyDisplayLimits) {
            $blockedClaims = $blockedClaims->take(6);
        }

        $blockedClaims->each(function (BillingClaimAudit $claim) use ($queueItem) {
            $name = $claim->client ? trim($claim->client->first_name.' '.$claim->client->last_name) : 'Client';
            $periodLabel = $claim->billing_period?->format('M Y') ?? 'billing period';
            $queueItem([
                'key' => 'claim-'.$claim->id,
                // Distinct from legacy invoice "Billing hold" so dashboard chips
                // mirror the Billing page's on-hold claim count (A3/A4).
                'kind' => 'Held claim',
                'title' => "Billing held — {$name}",
                'subtitle' => ($claim->claim_number ?? $claim->invoice_number ?? 'Claim').' · '.$periodLabel.' · CP-01 / blocked',
                'approveType' => null,
                'approveId' => null,
                'approveUrl' => null,
                'reviewUrl' => route('billing-claims-audit.show', $claim),
                'reviewKey' => 'claim|'.$claim->id,
                'canApprove' => false,
            ]);
        });

        $paRenewals = CareDetail::with('client')->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'Active');
            })
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $today->copy()->addDays(30))
            ->orderBy('end_date');

        if ($applyDisplayLimits) {
            $paRenewals->limit(6);
        }

        $paRenewals->get()->each(function ($cd) use ($queueItem, $today) {
            $name = $cd->client ? trim($cd->client->first_name.' '.$cd->client->last_name) : 'Client';
            $days = (int) $today->diffInDays(Carbon::parse($cd->end_date), false);
            $queueItem([
                'key' => 'pa-'.$cd->id,
                'kind' => 'PA renewal',
                'title' => "Renew Prior Authorization — {$name}",
                'subtitle' => 'Expires '.Carbon::parse($cd->end_date)->format('M j')." · {$days} days · packet assembled by agent",
                'approveType' => 'pa',
                'approveId' => $cd->id,
                'approveUrl' => route('dashboard.approve', ['type' => 'pa', 'id' => $cd->id]),
                'reviewUrl' => $cd->client
                    ? $this->clientReviewUrl($cd->client, 'authorization', ['care_detail' => $cd->id])
                    : '#',
                'canApprove' => true,
            ]);
        });

        $backgroundFlags = Employee::when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('has_background_check', 0);

        if ($applyDisplayLimits) {
            $backgroundFlags->limit(4);
        }

        $backgroundFlags->get()->each(function ($e) use ($queueItem) {
            $name = trim($e->first_name.' '.$e->last_name);
            $queueItem([
                'key' => 'background-'.$e->id,
                'kind' => 'Background flag',
                'title' => "OIG flag — {$name} (caregiver)",
                'subtitle' => 'Possible match · verify same-person by address before disqualifying',
                'approveType' => 'background',
                'approveId' => $e->id,
                'approveUrl' => route('dashboard.approve', ['type' => 'background', 'id' => $e->id]),
                'reviewUrl' => route('caregivers.show', ['id' => $e->id, 'tab' => 'checks']),
                'canApprove' => true,
            ]);
        });

        $clientActivations = Client::when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', 'Hold');

        if ($applyDisplayLimits) {
            $clientActivations->limit(4);
        }

        $clientActivations->get()->each(function ($c) use ($queueItem) {
            $name = trim($c->first_name.' '.$c->last_name);
            $queueItem([
                'key' => 'client-'.$c->id,
                'kind' => 'Client activation',
                'title' => "Activate client — {$name}",
                'subtitle' => 'Eligibility verified · ready to move to Active',
                'approveType' => 'client_activate',
                'approveId' => $c->id,
                'approveUrl' => route('dashboard.approve', ['type' => 'client_activate', 'id' => $c->id]),
                'reviewUrl' => route('clients.show', $c->id),
                'canApprove' => true,
            ]);
        });

        $agentTasks = Task::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('awaiting_approval', true)
            ->where('assignee_type', Task::ASSIGNEE_AGENT)
            ->with(['assigneeAgent', 'client', 'employee'])
            ->orderBy('updated_at');

        if ($applyDisplayLimits) {
            $agentTasks->limit(8);
        }

        $agentTasks->get()->each(function (Task $task) use ($queueItem) {
            $agentName = $task->assigneeAgent?->name ?? 'AI agent';
            $about = $task->client
                ? trim($task->client->first_name.' '.$task->client->last_name)
                : ($task->employee ? trim($task->employee->first_name.' '.$task->employee->last_name) : null);

            $queueItem([
                'key' => 'task-'.$task->id,
                'kind' => 'Agent task',
                'title' => $task->title,
                'subtitle' => $agentName.' completed work'.($about ? " · {$about}" : '').' · awaiting your approval',
                'approveType' => 'task',
                'approveId' => $task->id,
                'approveUrl' => route('dashboard.approve', ['type' => 'task', 'id' => $task->id]),
                'reviewUrl' => route('tasks', ['status' => 'open']),
                'canApprove' => true,
            ]);
        });

        return $items;
    }

    private function legacyItemToCard(array $item, Carbon $today): ?array
    {
        $slug = $item['key'];
        $sla = $this->estimateSla($item['kind'] ?? '');

        $category = match ($item['kind'] ?? '') {
            'Billing hold' => 'Billing hold',
            'Held claim' => 'Pre-billing gate · hold',
            'PA renewal' => 'Authorization renewal · send to MCO',
            'Background flag' => 'Background check · verify before disqualify',
            'Client activation' => 'Activate · new client',
            'Agent task' => 'Agent result · approve to mark Done',
            default => $item['kind'] ?? 'Approval',
        };

        $reasonTone = match ($item['kind'] ?? '') {
            'Billing hold', 'Held claim' => 'warn',
            'Background flag' => 'warn',
            'Agent task' => 'info',
            default => 'info',
        };

        $actions = $this->defaultActionsForKind($item);

        $context = [
            ['label' => 'Summary', 'value' => $item['subtitle'] ?? '—'],
        ];

        if (($item['kind'] ?? '') === 'PA renewal') {
            $context = [
                ['label' => 'Renewal', 'value' => $item['subtitle'] ?? '—'],
                ['label' => 'Packet', 'value' => 'Prepared by agent · eligibility re-verified'],
            ];
        }

        return [
            'slug' => $slug,
            'live' => true,
            'kind' => $item['kind'] ?? 'Approval',
            'category' => $category,
            'title' => $item['title'],
            'urgent' => in_array($sla['tone'], ['now'], true),
            'sla' => $sla,
            'context' => $context,
            'reason' => $this->reasonForKind($item),
            'reason_tone' => $reasonTone,
            'actions' => $actions,
            'review_label' => $this->reviewLabelForKind($item),
            'review_url' => $item['reviewUrl'] ?? null,
            'approve_type' => $item['approveType'] ?? null,
            'approve_id' => $item['approveId'] ?? null,
        ];
    }

    private function defaultActionsForKind(array $item): array
    {
        if (! ($item['canApprove'] ?? false)) {
            return [
                ['label' => 'Keep on hold', 'action' => 'hold', 'variant' => 'secondary'],
                ['label' => 'Open record', 'action' => 'review', 'variant' => 'secondary'],
            ];
        }

        $primary = match ($item['kind'] ?? '') {
            'Client activation' => '✓ Activate client',
            'Background flag' => '✓ Verified — clear',
            'PA renewal' => '✓ Approve & send',
            'Agent task' => '✓ Approve & mark Done',
            default => '✓ Approve',
        };

        $actions = [
            ['label' => $primary, 'action' => 'approve', 'variant' => 'success'],
            ['label' => 'Hold', 'action' => 'hold', 'variant' => 'secondary'],
        ];

        if (in_array($item['kind'] ?? '', ['Client activation', 'Background flag'], true)) {
            $actions[] = ['label' => 'Reject', 'action' => 'reject', 'variant' => 'danger'];
        }

        return $actions;
    }

    private function reasonForKind(array $item): string
    {
        return match ($item['kind'] ?? '') {
            'Billing hold', 'Held claim' => 'This month\'s bill is held pending CP-01 / blocked claim review. Investigate before billing.',
            'PA renewal' => 'Renewal request is due before PA end. Approve to submit the prepared packet.',
            'Background flag' => 'Possible OIG LEIE name match. Verify same-person by address/DOB before disqualifying.',
            'Client activation' => 'Eligibility verified and packet complete — approve to flip the client to Active and start service.',
            'Agent task' => 'An AI agent finished this task. Approve to mark it Done, or open Tasks to review and edit first.',
            default => $item['subtitle'] ?? 'Review and approve or hold.',
        };
    }

    private function reviewLabelForKind(array $item): string
    {
        return match ($item['kind'] ?? '') {
            'Background flag' => 'Open caregiver →',
            'PA renewal' => 'View authorization →',
            'Agent task' => 'Open tasks →',
            default => 'Open client →',
        };
    }

    /**
     * @return array{label: string, tone: string}
     */
    private function estimateSla(string $kind): array
    {
        return match ($kind) {
            'Background flag' => ['label' => 'Due in 3h', 'tone' => 'now'],
            'PA renewal' => ['label' => 'Due today', 'tone' => 'now'],
            'Billing hold', 'Held claim' => ['label' => 'Due in 10h', 'tone' => 'soon'],
            'Client activation' => ['label' => 'Due in 18h', 'tone' => 'soon'],
            'Agent task' => ['label' => 'Due in 12h', 'tone' => 'soon'],
            default => ['label' => 'Due in 20h', 'tone' => 'ok'],
        };
    }

    /**
     * @return list<string>
     */
    private function resolvedSlugs(?int $orgId, string $type): array
    {
        return WorkflowQueueItem::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('queue_type', $type)
            ->whereNot('status', WorkflowQueueItem::STATUS_PENDING)
            ->pluck('slug')
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $approvals
     * @return list<array{label: string}>
     */
    private function summariseApprovals(array $approvals, ?int $orgId, Carbon $today): array
    {
        $period = $today->copy()->startOfMonth();

        $chips = collect($approvals)
            ->groupBy('kind')
            ->map(fn ($group, $kind) => [
                'label' => $group->count().' '.Str::plural(strtolower($kind), $group->count()),
                'kind' => $kind,
            ])
            ->values()
            ->all();

        return $this->applyCanonicalChipCounts($chips, $orgId, $period);
    }

    /**
     * Replace billing-hold chip totals with the current-cycle counts from the
     * shared metrics service so Dashboard chips match the Billing page header.
     *
     * @param  list<array{label: string, kind?: string}>  $chips
     * @return list<array{label: string}>
     */
    private function applyCanonicalChipCounts(array $chips, ?int $orgId, Carbon $period): array
    {
        $canonical = [
            'Billing hold' => $this->metrics->pendingBillingHoldCount($orgId, $period),
            'Held claim' => $this->metrics->onHoldClaimCount($orgId, $period),
        ];

        $updated = collect($chips)->map(function (array $chip) use ($canonical) {
            $kind = $chip['kind'] ?? null;
            if ($kind && array_key_exists($kind, $canonical)) {
                $count = $canonical[$kind];
                if ($count === 0) {
                    return null;
                }

                return [
                    'label' => $count.' '.Str::plural(strtolower($kind), $count),
                ];
            }

            unset($chip['kind']);

            return $chip;
        })->filter()->values()->all();

        foreach ($canonical as $kind => $count) {
            if ($count === 0) {
                continue;
            }

            $label = strtolower($kind);
            $exists = collect($updated)->contains(fn (array $chip) => str_contains($chip['label'], $label));

            if (! $exists) {
                $updated[] = [
                    'label' => $count.' '.Str::plural($label, $count),
                ];
            }
        }

        return $updated;
    }

    private function resolveBillingReviewUrl(Billing $billing): string
    {
        $claim = $this->findClaimForBilling($billing);

        if ($claim) {
            return route('billing-claims-audit.show', $claim);
        }

        if ($billing->client_id) {
            return route('clients.show', [
                'id' => $billing->client_id,
                'tab' => 'billing',
                'billing' => $billing->id,
            ]);
        }

        $period = $billing->period_start
            ? Carbon::parse($billing->period_start)->format('Y-m')
            : ($billing->created_at?->format('Y-m') ?? now()->format('Y-m'));

        return route('billing-claims-audit.index', array_filter([
            'period' => $period,
            'search' => $billing->invoice_number ?: $billing->id,
            'status' => 'on_hold',
        ]));
    }

    private function clientReviewUrl(Client $client, string $tab, array $query = []): string
    {
        return route('clients.show', array_merge([
            'id' => $client->id,
            'tab' => $tab,
        ], $query));
    }

    private function findClaimForBilling(Billing $billing): ?BillingClaimAudit
    {
        $base = BillingClaimAudit::query()
            ->when($billing->organization_id, fn ($q) => $q->where('organization_id', $billing->organization_id))
            ->when($billing->client_id, fn ($q) => $q->where('client_id', $billing->client_id));

        if ($billing->invoice_number) {
            $match = (clone $base)
                ->where(function ($query) use ($billing) {
                    $query->where('invoice_number', $billing->invoice_number)
                        ->orWhere('claim_number', $billing->invoice_number);
                })
                ->latest('id')
                ->first();

            if ($match) {
                return $match;
            }
        }

        if ($billing->period_start) {
            $period = Carbon::parse($billing->period_start);

            return (clone $base)
                ->whereYear('billing_period', $period->year)
                ->whereMonth('billing_period', $period->month)
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function money(float|int|string|null $amount, bool $compact = false): string
    {
        $value = (float) ($amount ?? 0);

        if ($compact && $value >= 1000) {
            return '$'.number_format($value / 1000, 1).'k';
        }

        return '$'.number_format($value, 2);
    }
}
