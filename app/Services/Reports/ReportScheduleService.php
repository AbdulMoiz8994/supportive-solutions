<?php

namespace App\Services\Reports;

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReportScheduleService
{
    public function __construct(
        protected ReportsDataService $reports,
        protected ReportExportService $export,
        protected ReportRunService $runs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $user, array $payload, ?int $organizationId): ReportSchedule
    {
        $slug = $payload['report_slug'] ?? '';
        abort_if(! config("reports.reports.{$slug}"), 422, 'Unknown report.');

        $frequency = $payload['frequency'] ?? 'monthly';
        $schedule = ReportSchedule::create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'report_slug' => $slug,
            'custom_report_id' => $payload['custom_report_id'] ?? null,
            'frequency' => $frequency,
            'format' => $payload['format'] ?? 'csv',
            'recipients' => $this->normalizeRecipients($payload['recipients'] ?? [$user->email]),
            'filters' => $payload['filters'] ?? [],
            'is_active' => true,
            'next_run_at' => $this->nextRunAt($frequency),
        ]);

        return $schedule;
    }

    public function deactivate(ReportSchedule $schedule): void
    {
        $schedule->update(['is_active' => false]);
    }

    public function runDueSchedules(): int
    {
        $count = 0;

        ReportSchedule::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->with('user')
            ->each(function (ReportSchedule $schedule) use (&$count) {
                $this->execute($schedule);
                $count++;
            });

        return $count;
    }

    public function execute(ReportSchedule $schedule): void
    {
        $user = $schedule->user;
        $period = $this->reports->parsePeriod(now()->format('Y-m'));
        $definition = config('reports.reports.'.$schedule->report_slug, []);
        $definition['name'] = $definition['name'] ?? $schedule->report_slug;

        $data = $this->reports->report(
            $schedule->report_slug,
            $schedule->organization_id,
            $period,
            $schedule->filters ?? []
        );

        $rowCount = $this->export->countRows($data);
        $this->runs->record(
            $schedule->report_slug,
            $schedule->organization_id,
            $user?->id,
            $period,
            $schedule->format,
            $rowCount,
            $schedule->id,
            $schedule->custom_report_id,
        );

        foreach ($schedule->recipients ?? [] as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            Mail::to($email)->send(new ScheduledReportMail(
                $definition,
                $data,
                $period->format('F Y'),
                $schedule->format,
            ));
        }

        $schedule->update([
            'last_run_at' => now(),
            'next_run_at' => $this->nextRunAt($schedule->frequency),
        ]);
    }

    /**
     * @return Collection<int, ReportSchedule>
     */
    public function forUser(User $user, ?int $organizationId): Collection
    {
        return ReportSchedule::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->get();
    }

    public function nextRunAt(string $frequency): Carbon
    {
        return match ($frequency) {
            'weekly' => now()->addWeek()->startOfDay(),
            'quarterly' => now()->addMonths(3)->startOfMonth(),
            'per_run' => now()->addMonth(),
            default => now()->addMonth()->startOfMonth(),
        };
    }

    /**
     * @param  mixed  $recipients
     * @return list<string>
     */
    protected function normalizeRecipients(mixed $recipients): array
    {
        if (is_string($recipients)) {
            $recipients = preg_split('/[\s,;]+/', $recipients) ?: [];
        }

        $emails = collect(is_array($recipients) ? $recipients : [])
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        if ($emails === []) {
            throw ValidationException::withMessages(['recipients' => 'At least one valid email is required.']);
        }

        return $emails;
    }
}
