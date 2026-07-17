<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PayRecordResource;
use App\Models\PayRecord;
use App\Models\Schedule;
use App\Services\PayrollDocumentService;
use App\Services\VisitReportService;
use App\Support\Api\PayBreakdown;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayController extends Controller
{
    use ResolvesCaregiver;

    /**
     * The logged-in caregiver's pay history.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $caregiver = $this->caregiver();

        $request->validate([
            'period_key' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $records = PayRecord::query()
            ->where('employee_id', $caregiver->id)
            ->when($request->filled('period_key'), fn ($q) => $q->where('period_key', $request->query('period_key')))
            ->with('client')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return PayRecordResource::collection($records);
    }

    /**
     * A single paystub with an estimated gross → net breakdown and a
     * per-client visit summary for the pay period (paystub detail screen).
     */
    public function show(PayRecord $payRecord): JsonResponse
    {
        $caregiver = $this->caregiver();

        abort_unless((int) $payRecord->employee_id === (int) $caregiver->id, 403);

        $payRecord->loadMissing('client');
        $base = (new PayRecordResource($payRecord))->toArray(request());

        return response()->json([
            'data' => array_merge($base, [
                'pay_date' => optional($payRecord->paid_date)->toDateString(),
                'breakdown' => PayBreakdown::forGross($payRecord->gross !== null ? (float) $payRecord->gross : null),
                'visit_summary' => $this->visitSummary($caregiver, $payRecord->period_key),
            ]),
        ]);
    }

    /**
     * Hours per client within the paystub's period, drawn from completed visits.
     *
     * @return array<int, array{client_id: int|null, client_name: string, hours: float}>
     */
    private function visitSummary(\App\Models\Employee $caregiver, ?string $periodKey): array
    {
        if (! $periodKey || ! preg_match('/^\d{4}-\d{2}$/', $periodKey)) {
            return [];
        }

        $month = Carbon::createFromFormat('Y-m', $periodKey);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $visitReports = app(VisitReportService::class);

        return $caregiver->schedules()
            ->with('client')
            ->where('status', Schedule::STATUS_COMPLETED)
            ->whereBetween('start_at', [$start, $end])
            ->get()
            ->filter(fn (Schedule $schedule) => $visitReports->hasCleanTimeData($schedule))
            ->groupBy('client_id')
            ->map(fn ($rows) => [
                'client_id' => $rows->first()->client_id,
                'client_name' => trim(($rows->first()->client->first_name ?? '').' '.($rows->first()->client->last_name ?? '')) ?: 'Client',
                'hours' => round((float) $rows->sum(
                    fn (Schedule $schedule) => (float) ($visitReports->effectiveHours($schedule) ?? 0)
                ), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Download a pay stub PDF (only the caregiver's own).
     */
    public function stub(PayRecord $payRecord, PayrollDocumentService $documentService): BinaryFileResponse
    {
        $caregiver = $this->caregiver();

        abort_unless((int) $payRecord->employee_id === (int) $caregiver->id, 403);

        abort_unless($documentService->stubIsAvailable($payRecord), 404, 'Stub not available.');

        return $documentService->downloadResponse($payRecord);
    }
}
