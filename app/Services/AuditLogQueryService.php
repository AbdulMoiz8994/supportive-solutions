<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PayrollAuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuditLogQueryService
{
    /**
     * @return LengthAwarePaginator<int, array{when: string, actor: string, action: string, source: string}>
     */
    public function paginate(?int $organizationId, int $perPage = 25): LengthAwarePaginator
    {
        $entries = $this->collectEntries($organizationId);

        $page = max(1, (int) request('page', 1));
        $total = $entries->count();
        $items = $entries->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @return Collection<int, array{when: string, actor: string, action: string, source: string, sort: mixed}>
     */
    protected function collectEntries(?int $organizationId): Collection
    {
        $entries = collect();

        PayrollAuditLog::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->latest('occurred_at')
            ->limit(500)
            ->get()
            ->each(function (PayrollAuditLog $log) use ($entries) {
                $entries->push([
                    'when' => optional($log->occurred_at)->format('M j g:ia') ?? '—',
                    'actor' => $log->actor_name,
                    'action' => $log->detail ?: Str::headline(str_replace('_', ' ', $log->action)),
                    'source' => 'Payroll',
                    'sort' => $log->occurred_at ?? $log->created_at,
                ]);
            });

        ActivityLog::query()
            ->with('user')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->latest()
            ->limit(500)
            ->get()
            ->each(function (ActivityLog $log) use ($entries) {
                $entries->push([
                    'when' => optional($log->created_at)->format('M j g:ia') ?? '—',
                    'actor' => $log->user?->name ?? 'System',
                    'action' => $log->description ?? Str::headline(str_replace('_', ' ', (string) $log->action)),
                    'source' => 'Platform',
                    'sort' => $log->created_at,
                ]);
            });

        return $entries
            ->sortByDesc('sort')
            ->map(fn (array $row) => collect($row)->except('sort')->all())
            ->values();
    }

    public static function log(User $actor, string $action, mixed $subject = null, array $properties = [], ?int $organizationId = null): ActivityLog
    {
        return ActivityLog::create([
            'organization_id' => $organizationId ?? $actor->organization_id,
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->id,
            'description' => $action,
            'properties' => $properties ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
