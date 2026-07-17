<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\CareDetail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Task;
use App\Models\WorkflowQueueItem;
use App\Services\WorkflowQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WorkflowQueuesController extends Controller
{
    public function __construct(
        protected WorkflowQueueService $queues,
    ) {}

    public function index(Request $request)
    {
        $orgId = $this->organizationId();

        return view('pages.workflow-queues.index', array_merge(
            $this->queues->pageData($orgId, $request),
            ['csrfToken' => csrf_token()],
        ));
    }

    public function loadApprovals(Request $request): JsonResponse
    {
        $orgId = $this->organizationId();
        $limit = (int) config('workflow_queues.approvals_per_page', 12);
        $offset = max(0, $request->integer('offset', 0));
        $sort = $this->queues->normalizeSort($request->query('sort', 'sla'));
        $filter = $this->queues->normalizeFilter($request->query('filter'));

        $paged = $this->queues->approvalsPage($orgId, $sort, $filter, $offset, $limit);

        $html = view('pages.workflow-queues.partials.approval-cards', [
            'approvals' => $paged['approvals'],
        ])->render();

        if ($paged['approvals'] === [] && $offset === 0) {
            $html = view('pages.workflow-queues.partials.approval-empty')->render();
        }

        return response()->json([
            'ok' => true,
            'html' => $html,
            'approvalsMeta' => $paged['approvalsMeta'],
            'sectionApprovalsLabel' => $paged['sectionApprovalsLabel'],
            'sort' => $paged['sort'],
            'filter' => $paged['filter'],
        ]);
    }

    public function action(Request $request, string $slug)
    {
        $orgId = $this->organizationId();
        $action = $request->input('queue_action', $request->input('action', 'approve'));
        $approveType = $request->input('approve_type');
        $approveId = $request->integer('approve_id') ?: null;

        if ($approveType && $approveId) {
            return $this->handleLiveApproval($request, $slug, $approveType, $approveId, $orgId);
        }

        $result = $this->queues->applyAction($orgId, $slug, $action);

        $message = $this->queues->actionMessage($slug, $action);

        return $this->respond($request, $orgId, $message, $slug);
    }

    private function handleLiveApproval(Request $request, string $slug, string $type, int $id, ?int $orgId): JsonResponse|RedirectResponse
    {
        $model = match ($type) {
            'billing' => Billing::query()->with('client')->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id),
            'pa' => CareDetail::query()->with('client')->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id),
            'client_activate' => Client::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id),
            'background' => Employee::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id),
            'task' => Task::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id),
            default => null,
        };

        if ($model === null) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Unknown approval type.'], 422);
            }

            return back()->with('error', 'Unknown approval type.');
        }

        $queueAction = $request->input('queue_action', $request->input('action', 'approve'));

        if ($queueAction === 'hold') {
            $this->queues->applyAction($orgId, $slug, 'hold');

            return $this->respond(
                $request,
                $orgId,
                $this->queues->actionMessage($slug, 'hold', $type, $model),
                $slug,
            );
        }

        if ($queueAction === 'reject') {
            if ($type === 'task') {
                $model->update(['awaiting_approval' => false]);
            }

            $this->queues->applyAction($orgId, $slug, 'reject');

            return $this->respond(
                $request,
                $orgId,
                $this->queues->actionMessage($slug, 'reject', $type, $model),
                $slug,
            );
        }

        match ($type) {
            'billing' => $model->update(['status' => 'Sent']),
            'pa' => $model->update([
                'end_date' => Carbon::parse($model->end_date)->addMonths(6)->toDateString(),
                'status' => 'Active',
            ]),
            'client_activate' => $model->update(['status' => 'Active']),
            'background' => $model->update(['has_background_check' => 1]),
            'task' => $model->markDone(),
            default => null,
        };

        return $this->respond(
            $request,
            $orgId,
            $this->queues->actionMessage($slug, 'approve', $type, $model),
            $slug,
        );
    }

    private function respond(Request $request, ?int $orgId, string $message, ?string $removedSlug = null): JsonResponse|RedirectResponse
    {
        \App\Helpers\MenuHelper::forgetBadgeCache($orgId);

        if ($request->expectsJson()) {
            return response()->json(array_merge(
                ['ok' => true, 'message' => $message, 'removedSlug' => $removedSlug],
                $this->queues->queueSnapshot($orgId, includeLists: false),
            ));
        }

        return redirect()
            ->route('workflow-queues')
            ->with('success', $message);
    }

    private function organizationId(): ?int
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() ? null : $user?->organization_id;
    }
}
