<?php

namespace App\Http\Controllers;

use App\Services\TaskBoardStatusService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TasksController extends Controller
{
    public function __construct(
        protected TaskService $tasks,
        protected TaskBoardStatusService $boardStatuses,
    ) {}

    public function index(Request $request)
    {
        $orgId = $this->organizationId();
        $this->tasks->syncAuthorizationTasks($orgId);
        $this->tasks->syncComplianceTasks($orgId);
        $this->tasks->syncDocumentExpiryTasks($orgId);

        return view('pages.tasks.index', $this->tasks->pageData($orgId, $request));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
            'assignee_type' => 'required|in:user,agent',
            'assignee_user_id' => 'nullable|integer|exists:users,id',
            'assignee_agent_id' => 'nullable|integer|exists:ai_agents,id',
            'client_id' => 'nullable|integer|exists:clients,id',
            'employee_id' => 'nullable|integer|exists:employees,id',
        ]);

        $task = $this->tasks->store($this->organizationId(), $validated, $request->user());

        return $this->respond($request, 'Task created.', $task->id);
    }

    public function show(int $id): JsonResponse
    {
        $orgId = $this->organizationId();
        $task = $this->tasks->findTask($orgId, $id);

        return response()->json($this->tasks->taskDetailPayload($orgId, $task));
    }

    public function comments(int $id): JsonResponse
    {
        $orgId = $this->organizationId();

        return response()->json([
            'ok' => true,
            'comments' => $this->tasks->listComments($orgId, $id),
        ]);
    }

    public function storeComment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $orgId = $this->organizationId();
        $comment = $this->tasks->addComment($orgId, $id, $request->user(), $validated['body']);

        return response()->json([
            'ok' => true,
            'message' => 'Comment added.',
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'user_name' => $request->user()->name,
                'created_at' => $comment->created_at?->format('M j, Y g:i A'),
            ],
            'comments' => $this->tasks->listComments($orgId, $id),
        ]);
    }

    public function submitForApproval(Request $request, int $id): JsonResponse
    {
        $orgId = $this->organizationId();

        try {
            $task = $this->tasks->submitAgentResultForApproval($orgId, $id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Task submitted for human approval.',
            'task' => $this->tasks->taskDetailPayload($orgId, $task)['task'],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $orgId = $this->organizationId();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'status' => 'required|in:'.implode(',', $this->tasks->validBoardStatusKeys($orgId)),
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
            'assignee_type' => 'required|in:user,agent',
            'assignee_user_id' => 'nullable|integer|exists:users,id',
            'assignee_agent_id' => 'nullable|integer|exists:ai_agents,id',
        ]);

        $task = $this->tasks->update($orgId, $id, $validated);

        return response()->json($this->tasks->taskUpdatePayload($orgId, $task));
    }

    public function updateStatus(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $orgId = $this->organizationId();
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', $this->tasks->validBoardStatusKeys($orgId)),
        ]);

        try {
            $task = $this->tasks->updateStatus($orgId, $id, $validated['status']);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json($this->tasks->statusMovePayload($orgId, $task));
        }

        return $this->respond($request, 'Task moved.', null, $request->input('view', 'board'));
    }

    public function storeBoardStatus(Request $request): JsonResponse
    {
        $orgId = $this->organizationId();
        $validated = $request->validate([
            'label' => 'required|string|max:80',
            'key' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('task_board_statuses', 'key')->where('organization_id', $orgId),
            ],
            'is_closed' => 'boolean',
        ]);

        $this->boardStatuses->store($orgId, $validated);

        return response()->json(array_merge(
            ['ok' => true, 'message' => 'Status added.'],
            $this->tasks->boardStructurePayload($orgId, $request),
        ));
    }

    public function reorderBoardStatuses(Request $request): JsonResponse
    {
        $orgId = $this->organizationId();
        $validated = $request->validate([
            'order' => 'required|array|min:1',
            'order.*' => 'integer',
        ]);

        try {
            $this->boardStatuses->reorder($orgId, $validated['order']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            ['ok' => true, 'message' => 'Status order saved.'],
            $this->tasks->boardStructurePayload($orgId, $request),
        ));
    }

    public function updateBoardStatus(Request $request, int $statusId): JsonResponse
    {
        $orgId = $this->organizationId();
        $validated = $request->validate([
            'label' => 'required|string|max:80',
            'is_closed' => 'boolean',
        ]);

        $this->boardStatuses->update($orgId, $statusId, $validated);

        return response()->json(array_merge(
            ['ok' => true, 'message' => 'Status updated.'],
            $this->tasks->boardStructurePayload($orgId, $request),
        ));
    }

    public function destroyBoardStatus(Request $request, int $statusId): JsonResponse
    {
        $orgId = $this->organizationId();

        try {
            $this->boardStatuses->delete($orgId, $statusId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            ['ok' => true, 'message' => 'Status removed.'],
            $this->tasks->boardStructurePayload($orgId, $request),
        ));
    }

    private function respond(Request $request, string $message, ?int $taskId = null, ?string $view = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $orgId = $this->organizationId();
            $pageRequest = $request->duplicate(['view' => $view ?? $request->input('view', 'list')]);

            return response()->json(array_merge(
                ['ok' => true, 'message' => $message, 'task_id' => $taskId],
                $this->tasks->pageData($orgId, $pageRequest),
            ));
        }

        return redirect()->route('tasks', ['view' => $view ?? $request->input('view')])->with('success', $message);
    }

    private function organizationId(): ?int
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() ? null : $user?->organization_id;
    }
}
