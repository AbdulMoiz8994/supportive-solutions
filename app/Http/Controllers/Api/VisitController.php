<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VisitResource;
use App\Models\Client;
use App\Models\Schedule;
use App\Services\ScheduleClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clock in / clock out. This platform is the source of truth for visit hours,
 * which feed both billing (claims) and payroll.
 */
class VisitController extends Controller
{
    use ResolvesCaregiver;

    public function __construct(
        protected ScheduleClockService $clockService,
    ) {}

    /**
     * Recent visits (completed + in-progress) for the caregiver.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $caregiver = $this->caregiver();

        $records = $caregiver->schedules()
            ->with('client')
            ->whereIn('status', [
                Schedule::STATUS_COMPLETED,
                Schedule::STATUS_CLOCKED_IN,
                Schedule::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('actual_clock_in')
            ->paginate($request->integer('per_page', 50));

        return VisitResource::collection($records);
    }

    /**
     * The caregiver's currently open (clocked-in) visit, or null.
     */
    public function active(): JsonResponse
    {
        $caregiver = $this->caregiver();

        $visit = $caregiver->schedules()
            ->with('client')
            ->whereIn('status', Schedule::inProgressStatuses())
            ->orderByDesc('actual_clock_in')
            ->first();

        return response()->json([
            'data' => $visit ? new VisitResource($visit) : null,
        ]);
    }

    /**
     * Clock in — either into an existing scheduled visit (schedule_id)
     * or ad-hoc against an assigned client (client_id).
     */
    public function clockIn(Request $request): JsonResponse
    {
        $caregiver = $this->caregiver();

        $validated = $request->validate([
            'schedule_id' => ['nullable', 'integer'],
            'client_id'   => ['required_without:schedule_id', 'nullable', 'integer'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Only one open visit at a time (anti-fraud / integrity).
        $alreadyOpen = $caregiver->schedules()
            ->whereIn('status', Schedule::inProgressStatuses())
            ->exists();

        if ($alreadyOpen) {
            return response()->json(
                ['message' => 'You are already clocked in. Clock out first.'],
                Response::HTTP_CONFLICT
            );
        }

        if (! empty($validated['schedule_id'])) {
            $schedule = $caregiver->schedules()->find($validated['schedule_id']);

            if (! $schedule) {
                return response()->json(['message' => 'Visit not found.'], Response::HTTP_NOT_FOUND);
            }

            if (in_array($schedule->status, [Schedule::STATUS_COMPLETED, Schedule::STATUS_CANCELLED], true)) {
                return response()->json(['message' => 'This visit is already closed.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            $clientId = (int) $validated['client_id'];

            if (! $this->assignedClientIds($caregiver)->contains($clientId)) {
                return response()->json(['message' => 'This client is not assigned to you.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $client = Client::find($clientId);

            if (! $client) {
                return response()->json(['message' => 'Client not found.'], Response::HTTP_NOT_FOUND);
            }

            $schedule = Schedule::create([
                'organization_id' => $caregiver->organization_id,
                'client_id'   => $client->id,
                'employee_id' => $caregiver->id,
                'event_type'  => Schedule::EVENT_CARE_VISIT,
                'title'       => 'Care visit — '.trim("{$client->first_name} {$client->last_name}"),
                'date'        => now()->toDateString(),
                'start_at'    => now(),
                'address'     => $client->address,
                'location_id' => $caregiver->location_id,
                'status'      => Schedule::STATUS_SCHEDULED,
            ]);
        }

        try {
            $schedule = $this->clockService->clockIn(
                $schedule,
                isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            );
        } catch (ValidationException $exception) {
            return response()->json(
                ['message' => collect($exception->errors())->flatten()->first()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return (new VisitResource($schedule->load('client')))
            ->additional(['message' => 'Clocked in.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Clock out of the open visit (or a specific schedule_id).
     * Computes total_hours from the clock-in timestamp.
     */
    public function clockOut(Request $request): JsonResponse
    {
        $caregiver = $this->caregiver();

        $validated = $request->validate([
            'schedule_id' => ['nullable', 'integer'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ]);

        $query = $caregiver->schedules()->whereIn('status', Schedule::inProgressStatuses());

        $schedule = ! empty($validated['schedule_id'])
            ? (clone $query)->whereKey($validated['schedule_id'])->first()
            : $query->orderByDesc('actual_clock_in')->first();

        if (! $schedule) {
            return response()->json(['message' => 'No active visit to clock out.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $schedule = $this->clockService->clockOut(
                $schedule,
                $validated['notes'] ?? null,
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
            );
        } catch (ValidationException $exception) {
            return response()->json(
                ['message' => collect($exception->errors())->flatten()->first()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (! empty($validated['notes'])) {
            $notes = is_array($schedule->visit_notes) ? $schedule->visit_notes : [];
            $notes['caregiver_note'] = $validated['notes'];
            $schedule->update(['visit_notes' => $notes]);
        }

        return (new VisitResource($schedule->load('client')))
            ->additional(['message' => 'Clocked out.'])
            ->response();
    }
}
