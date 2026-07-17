<?php

namespace App\Services\Communication;

use App\Models\Communication;
use App\Models\Contact;
use App\Support\CommunicationPresenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CommunicationDashboardService
{
    public function resolvePeriod(?string $period): Carbon
    {
        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    public function periodOptions(Carbon $period): array
    {
        return [
            ['value' => $period->format('Y-m'), 'label' => $period->format('M Y')],
            ['value' => 'this_week', 'label' => 'This week'],
            ['value' => 'today', 'label' => 'Today'],
            ['value' => $period->copy()->subMonth()->format('Y-m'), 'label' => $period->copy()->subMonth()->format('M Y')],
        ];
    }

    public function baseQuery(Carbon $period, ?string $periodFilter = null): Builder
    {
        $query = Communication::query();

        if ($periodFilter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($periodFilter === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } else {
            $query->whereBetween('created_at', [
                $period->copy()->startOfMonth(),
                $period->copy()->endOfMonth()->endOfDay(),
            ]);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Carbon $period, ?string $periodFilter = null): array
    {
        $base = $this->baseQuery($period, $periodFilter);
        $total = (clone $base)->count();

        $needReply = (clone $base)->where(function (Builder $q) {
            $q->whereIn('status', [Communication::STATUS_RECEIVED, Communication::STATUS_QUEUED, Communication::STATUS_FAILED])
                ->orWhereJsonContains('metadata->handled_by', 'needs_review');
        })->count();

        $aiHandled = (clone $base)->where(function (Builder $q) {
            $q->whereIn('status', [Communication::STATUS_SENT, Communication::STATUS_READ])
                ->orWhereJsonContains('metadata->handled_by', 'ai_va');
        })->count();

        $efax = (clone $base)->where('channel', Communication::CHANNEL_FAX)->count();
        $wellnessTotal = (clone $base)->where('channel', Communication::CHANNEL_CALL)
            ->where(function (Builder $q) {
                $q->where('subject', 'like', '%wellness%')
                    ->orWhereJsonContains('metadata->wellness_call', true);
            })->count();

        $wellnessCompleted = (clone $base)->where('channel', Communication::CHANNEL_CALL)
            ->where(function (Builder $q) {
                $q->where('subject', 'like', '%wellness%')
                    ->orWhereJsonContains('metadata->wellness_call', true);
            })
            ->whereIn('status', [Communication::STATUS_SENT, Communication::STATUS_READ, Communication::STATUS_RECEIVED])
            ->count();

        $concerns = (clone $base)->where(function (Builder $q) {
            $q->whereJsonContains('metadata->handled_by', 'concern')
                ->orWhereNotNull('metadata->concern');
        })->count();

        $aiPercent = $total > 0 ? (int) round(($aiHandled / $total) * 100) : 0;

        return [
            'total' => $total,
            'need_reply' => $needReply,
            'ai_handled' => $aiHandled,
            'ai_percent' => $aiPercent,
            'efax' => $efax,
            'wellness_completed' => $wellnessCompleted,
            'wellness_total' => max($wellnessTotal, $wellnessCompleted),
            'wellness_pending' => max($wellnessTotal - $wellnessCompleted, 0),
            'concerns' => $concerns,
            'wellness_reached_percent' => $wellnessTotal > 0
                ? (int) round(($wellnessCompleted / $wellnessTotal) * 100)
                : 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function channelCounts(Carbon $period, ?string $periodFilter = null): array
    {
        $base = $this->baseQuery($period, $periodFilter);

        return [
            'all' => (clone $base)->count(),
            'need_reply' => (clone $base)->where(function (Builder $q) {
                $q->whereIn('status', [Communication::STATUS_RECEIVED, Communication::STATUS_QUEUED, Communication::STATUS_FAILED])
                    ->orWhereJsonContains('metadata->handled_by', 'needs_review');
            })->count(),
            'call' => (clone $base)->where('channel', Communication::CHANNEL_CALL)->count(),
            'sms' => (clone $base)->where('channel', Communication::CHANNEL_SMS)->count(),
            'fax' => (clone $base)->where('channel', Communication::CHANNEL_FAX)->count(),
            'email' => (clone $base)->where('channel', Communication::CHANNEL_EMAIL)->count(),
            'wellness' => (clone $base)->where('channel', Communication::CHANNEL_CALL)
                ->where(function (Builder $q) {
                    $q->where('subject', 'like', '%wellness%')
                        ->orWhereJsonContains('metadata->wellness_call', true);
                })->count(),
        ];
    }

    public function applyTabFilter(Builder $query, ?string $tab): Builder
    {
        if (! $tab || $tab === 'all') {
            return $query;
        }

        if ($tab === 'need_reply') {
            return $query->where(function (Builder $q) {
                $q->whereIn('status', [Communication::STATUS_RECEIVED, Communication::STATUS_QUEUED, Communication::STATUS_FAILED])
                    ->orWhereJsonContains('metadata->handled_by', 'needs_review');
            });
        }

        if ($tab === 'wellness') {
            return $query->where('channel', Communication::CHANNEL_CALL)
                ->where(function (Builder $q) {
                    $q->where('subject', 'like', '%wellness%')
                        ->orWhereJsonContains('metadata->wellness_call', true);
                });
        }

        if ($tab === 'fax') {
            return $query->where('channel', Communication::CHANNEL_FAX);
        }

        return $query->where('channel', $tab);
    }

    public function applyPartyFilter(Builder $query, ?string $party): Builder
    {
        return match ($party) {
            'client' => $query->where('related_type', \App\Models\Client::class),
            'caregiver' => $query->where('related_type', \App\Models\Employee::class),
            'case_coordinator' => $query->where(function (Builder $q) {
                $q->whereJsonContains('metadata->party_type', 'case_coordinator')
                    ->orWhere('recipient_type', Contact::class);
            }),
            'mco_portal' => $query->whereJsonContains('metadata->party_type', 'mco_portal'),
            'needs_review' => $query->where(function (Builder $q) {
                $q->whereJsonContains('metadata->handled_by', 'needs_review')
                    ->orWhereIn('status', [Communication::STATUS_QUEUED, Communication::STATUS_FAILED]);
            }),
            default => $query,
        };
    }

    /**
     * @param  Collection<int, Communication>  $communications
     */
    public function presenters(Collection $communications): Collection
    {
        return $communications->map(fn (Communication $c) => CommunicationPresenter::make($c));
    }
}
