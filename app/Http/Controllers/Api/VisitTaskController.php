<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VisitTaskResource;
use App\Models\Schedule;
use App\Models\VisitTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Care-task checklist for a visit: the "Care Tasks" list on the active shift,
 * the "Confirm Completed Tasks" step at clock-out, and the home task ring.
 */
class VisitTaskController extends Controller
{
    use ResolvesCaregiver;

    /**
     * Tasks for one of the caregiver's visits, in checklist order.
     */
    public function index(Schedule $schedule): AnonymousResourceCollection
    {
        $this->authorizeVisit($schedule);

        $tasks = VisitTask::query()
            ->where('schedule_id', $schedule->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return VisitTaskResource::collection($tasks);
    }

    /**
     * Add one task, or a batch, to a visit. Accepts either
     * { "label": "...", "category": "..." } or { "tasks": [ {label, category}, ... ] }.
     */
    public function store(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorizeVisit($schedule);

        $data = $request->validate([
            'label' => ['required_without:tasks', 'nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'tasks' => ['required_without:label', 'nullable', 'array', 'min:1'],
            'tasks.*.label' => ['required', 'string', 'max:255'],
            'tasks.*.category' => ['nullable', 'string', 'max:100'],
        ]);

        $rows = ! empty($data['tasks'])
            ? $data['tasks']
            : [['label' => $data['label'], 'category' => $data['category'] ?? null]];

        $start = (int) VisitTask::where('schedule_id', $schedule->id)->max('sort_order');

        $created = collect($rows)->values()->map(fn ($row, $i) => VisitTask::create([
            'organization_id' => $schedule->organization_id,
            'schedule_id' => $schedule->id,
            'client_id' => $schedule->client_id,
            'label' => $row['label'],
            'category' => $row['category'] ?? null,
            'sort_order' => $start + $i + 1,
        ]));

        return response()->json([
            'message' => 'Tasks added.',
            'data' => VisitTaskResource::collection($created)->toArray($request),
        ], 201);
    }

    /**
     * Flip a task's completed state (tap on the checklist).
     */
    public function toggle(Request $request, Schedule $schedule, VisitTask $task): JsonResponse
    {
        $this->authorizeVisit($schedule);
        abort_unless((int) $task->schedule_id === (int) $schedule->id, 404);

        $completed = $request->has('is_completed')
            ? $request->boolean('is_completed')
            : ! $task->is_completed;

        $task->update([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
            'completed_by' => $completed ? $this->caregiver()->id : null,
        ]);

        return response()->json([
            'message' => $completed ? 'Task completed.' : 'Task reopened.',
            'data' => (new VisitTaskResource($task))->toArray($request),
        ]);
    }

    /**
     * The visit must belong to the logged-in caregiver.
     */
    private function authorizeVisit(Schedule $schedule): void
    {
        abort_unless((int) $schedule->employee_id === (int) $this->caregiver()->id, 403);
    }
}
