<?php

namespace App\Http\Controllers;

use App\Services\VisitReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VisitReportsController extends Controller
{
    public function __construct(
        protected VisitReportService $reports,
    ) {}

    public function index(Request $request)
    {
        $orgId = $this->organizationId();

        return view('pages.visit-reports.index', $this->reports->pageData($orgId, $request));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $detail = $this->reports->detail($this->organizationId(), $id, $request->user());

        if (! $detail) {
            return response()->json(['ok' => false, 'message' => 'Visit not found.'], 404);
        }

        return response()->json(['ok' => true, 'visit' => $detail]);
    }

    public function proposeCorrection(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $schedule = $this->findScheduleOrFail($id);
        $this->authorize('manageVisitReports', \App\Models\Schedule::class);

        $validated = $request->validate([
            'field' => 'required|in:actual_clock_in,actual_clock_out',
            'proposed_time' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        $detail = $this->reports->proposeTimeCorrection(
            $this->organizationId(),
            $id,
            $request->user(),
            $validated['field'],
            $validated['proposed_time'],
            $validated['reason'],
        );

        return $this->respond($request, 'Time correction submitted for approval.', ['visit' => $detail]);
    }

    public function approveCorrection(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $schedule = $this->findScheduleOrFail($id);
        $this->authorize('manageVisitReports', \App\Models\Schedule::class);

        $detail = $this->reports->approveTimeCorrection(
            $this->organizationId(),
            $id,
            $request->user(),
        );

        return $this->respond($request, 'Time correction approved. Visit updated.', ['visit' => $detail]);
    }

    public function approveLocation(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $schedule = $this->findScheduleOrFail($id);
        $this->authorize('approveLocation', $schedule);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $detail = $this->reports->approveLocationOverride(
                $this->organizationId(),
                $id,
                $request->user(),
                $validated['reason'],
            );
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        return $this->respond($request, 'Location mismatch approved. Original GPS preserved.', ['visit' => $detail]);
    }

    public function markMissed(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $schedule = $this->findScheduleOrFail($id);
        $this->authorize('manageVisitReports', \App\Models\Schedule::class);

        $detail = $this->reports->markMissed($this->organizationId(), $id);

        return $this->respond($request, 'Visit marked as missed. Follow-up task created.', ['visit' => $detail]);
    }

    private function respond(Request $request, string $message, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['ok' => true, 'message' => $message], $extra));
        }

        return back()->with('success', $message);
    }

    private function findScheduleOrFail(int $id): \App\Models\Schedule
    {
        $orgId = $this->organizationId();

        return \App\Models\Schedule::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->findOrFail($id);
    }

    private function organizationId(): ?int
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() ? null : $user?->organization_id;
    }
}
