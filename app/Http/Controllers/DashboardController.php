<?php

namespace App\Http\Controllers;

use App\Models\Intake;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Billing;
use App\Models\BillingClaimAudit;
use App\Models\Schedule;
use App\Models\CareDetail;
use App\Models\Document;
use App\Models\ActivityLog;
use App\Models\PayRecord;
use App\Models\Task;
use App\Services\RegistryMetricsService;
use App\Services\WorkflowQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        protected RegistryMetricsService $registryMetrics,
        protected WorkflowQueueService $workflowQueues,
    ) {}

    /**
     * Live Dashboard — the BeydounTech operational overview.
     * Every figure below is computed from real records.
     */
    public function index()
    {
        $user  = auth()->user();
        // Super admins see every organisation; everyone else is scoped to theirs.
        $orgId = $user->isSuperAdmin() ? null : $user->organization_id;
        $now   = Carbon::now();
        $today = $now->copy()->startOfDay();

        // ── Greeting ──────────────────────────────────────────────────────────
        $hour = (int) $now->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = trim(explode(' ', trim($user->name ?? 'there'))[0]) ?: 'there';

        // ── KPI strip (shared metrics with client/caregiver registries) ───────
        $clientStats = $this->registryMetrics->clientStats();
        $caregiverStats = $this->registryMetrics->caregiverStats();
        $activeClients = $clientStats['active'];

        $activeClientRows = $this->registryMetrics->clients()->filter(function (Client $client) {
            $status = strtolower((string) ($client->statusRecord?->name ?? $client->status ?? 'Active'));

            return $status === 'active';
        });
        $countyMix = $activeClientRows
            ->groupBy(fn (Client $client) => $client->county ?: 'N/A')
            ->map->count()
            ->sortDesc()
            ->take(2);
        $clientsSub = $countyMix->isNotEmpty()
            ? $countyMix->map(fn ($c, $county) => "$c $county")->implode(' · ')
            : 'No active clients yet';

        $activeCaregivers = $caregiverStats['active'];
        $onLeaveCaregivers = $caregiverStats['on_leave'];
        $caregiversSub = "{$activeCaregivers} active · {$onLeaveCaregivers} on leave";

        $billingClaimStats = $this->registryMetrics->billingClaimStats($orgId, $now->copy()->startOfMonth());
        $billed = $billingClaimStats['billed_amount'];
        $collected = $billingClaimStats['collected_amount'];
        $arOutstanding = $billingClaimStats['outstanding_amount'];
        $billsInFlight = $billingClaimStats['in_flight'];

        // Document compliance = verified / total documents.
        $docTotal    = Document::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count();
        $docVerified = Document::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('verification_status', 'Verified')->count();
        $compliancePct = $docTotal > 0 ? round($docVerified / $docTotal * 100, 1) : 100.0;

        // Visit automation (EVV auto-verified completed visits) — a real operational proxy.
        $completedVisits = Schedule::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', 'Completed')->count();
        $evvVerified = Schedule::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', 'Completed')->where('evv_status', 1)->count();
        $automationPct = $completedVisits > 0 ? round($evvVerified / $completedVisits * 100, 1) : 100.0;

        $kpis = [
            ['icon' => 'clients',  'label' => 'Active Clients',  'value' => number_format($activeClients), 'sub' => $clientsSub],
            ['icon' => 'care',     'label' => 'Active caregivers', 'value' => number_format($activeCaregivers), 'sub' => $caregiversSub],
            ['icon' => 'billed',   'label' => 'Billed (' . $now->format('M') . ')', 'value' => $this->money($billed, true), 'sub' => $this->money($collected, true) . ' collected'],
            ['icon' => 'ar',       'label' => 'AR outstanding',  'value' => $this->money($arOutstanding, true), 'sub' => $billsInFlight . ' bills in flight'],
            ['icon' => 'shield',   'label' => 'Compliance',      'value' => $compliancePct . '%', 'sub' => "{$docVerified}/{$docTotal} verified"],
            ['icon' => 'bolt',     'label' => 'EVV automation',  'value' => $automationPct . '%', 'sub' => "{$evvVerified}/{$completedVisits} visits auto-verified"],
        ];

        // ── Approval queue ────────────────────────────────────────────────────
        $approvalPayload = $this->workflowQueues->approvalPayload($orgId, $today);
        $approvals = $approvalPayload['approvals'];
        $approvalChips = $approvalPayload['approvalChips'];
        $approvalCount = $approvalPayload['approvalCount'];

        // ── Coming up ─────────────────────────────────────────────────────────
        $comingUp = $this->buildComingUp($orgId, $today, $now);

        // ── Financial snapshot ────────────────────────────────────────────────
        $periodKey = $now->format('Y-m');
        $payrollGross = PayRecord::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('period_key', $periodKey)
            ->sum('gross');
        $margin = $billed > 0 ? (int) round(($billed - $payrollGross) / $billed * 100) : 0;
        $financial = [
            'period'      => $now->format('F'),
            'billed'      => $billed,
            'collected'   => $collected,
            'outstanding' => $arOutstanding,
            'margin'      => $margin,
            'payroll'     => $payrollGross,
        ];

        // ── Fleet / operational health ────────────────────────────────────────
        $totalVisits  = Schedule::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->whereIn('status', ['Completed', 'Missed'])->count();
        $missedVisits = Schedule::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', 'Missed')->count();
        $uptime   = $totalVisits > 0 ? round(($totalVisits - $missedVisits) / $totalVisits * 100, 1) : 100.0;
        $missRate = $totalVisits > 0 ? round($missedVisits / $totalVisits * 100, 1) : 0.0;
        $fleet = [
            'automation' => $automationPct,
            'uptime'     => $uptime,
            'missRate'   => $missRate,
            'note'       => "{$activeCaregivers} caregivers active · {$missedVisits} visit" . ($missedVisits === 1 ? '' : 's') . ' missed',
        ];

        // ── Recent activity ───────────────────────────────────────────────────
        $recentActivity = $this->buildRecentActivity($orgId);

        return view('pages.dashboard.index', [
            'title'          => 'Live Dashboard',
            'greeting'       => $greeting,
            'firstName'      => $firstName,
            'todayLabel'     => $now->format('l, F j, Y'),
            'kpis'           => $kpis,
            'approvals'      => $approvals,
            'approvalCount'  => $approvalCount,
            'approvalChips'  => $approvalChips,
            'csrfToken'      => csrf_token(),
            'comingUp'       => $comingUp,
            'financial'      => $financial,
            'fleet'          => $fleet,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Apply an approval action coming from the dashboard queue.
     */
    public function approve(Request $request, string $type, int $id)
    {
        $user  = auth()->user();
        $orgId = $user->isSuperAdmin() ? null : $user->organization_id;
        $message = 'Item approved.';

        switch ($type) {
            case 'billing':
                $b = Billing::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id);
                $b->update(['status' => 'Sent']);
                $message = "Invoice {$b->invoice_number} approved and queued to send.";
                break;

            case 'pa':
                $cd = CareDetail::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id);
                $cd->update([
                    'end_date' => Carbon::parse($cd->end_date)->addMonths(6)->toDateString(),
                    'status'   => 'Active',
                ]);
                $message = 'Authorization renewal approved (extended 6 months).';
                break;

            case 'client_activate':
                $c = Client::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id);
                $c->update(['status' => 'Active']);
                $message = trim($c->first_name . ' ' . $c->last_name) . ' activated.';
                break;

            case 'background':
                $e = Employee::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id);
                $e->update(['has_background_check' => 1]);
                $message = 'Background check cleared.';
                break;

            case 'task':
                $task = Task::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->findOrFail($id);
                $task->markDone();
                $message = 'Task "'.$task->title.'" approved and marked Done.';
                break;

            default:
                return back()->with('error', 'Unknown approval type.');
        }

        \App\Helpers\MenuHelper::forgetBadgeCache($orgId);

        if ($request->expectsJson()) {
            $approvals = $this->workflowQueues->approvalPayload($orgId, Carbon::now()->startOfDay());

            return response()->json([
                'ok' => true,
                'message' => $message,
                'approvalCount' => $approvals['approvalCount'],
                'approvalChips' => $approvals['approvalChips'],
                'approvals' => $approvals['approvals'],
            ]);
        }

        return redirect()->route('dashboard')->with('success', $message);
    }

    // ── Builders ────────────────────────────────────────────────────────────

    private function buildComingUp(?int $orgId, Carbon $today, Carbon $now): array
    {
        $items = [];

        // Upcoming scheduled visits.
        Schedule::with(['client'])->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', 'Scheduled')->whereDate('date', '>=', $today)
            ->orderBy('date')->limit(3)->get()
            ->each(function ($s) use (&$items) {
                $d = Carbon::parse($s->date);
                $name = $s->client ? trim($s->client->first_name . ' ' . $s->client->last_name) : 'Client';
                $items[] = [
                    'day'   => $d->format('j'),
                    'dow'   => $d->format('D'),
                    'title' => "Visit — {$name}",
                    'meta'  => Carbon::parse($s->start_time)->format('g:i A') . ' · scheduled',
                ];
            });

        // Authorizations coming due (renewal window).
        CareDetail::with('client')->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->whereDate('end_date', '>=', $today)->whereDate('end_date', '<=', $today->copy()->addDays(60))
            ->orderBy('end_date')->limit(3)->get()
            ->each(function ($cd) use (&$items) {
                $d = Carbon::parse($cd->end_date);
                $name = $cd->client ? trim($cd->client->first_name . ' ' . $cd->client->last_name) : 'Client';
                $items[] = [
                    'day'   => $d->format('j'),
                    'dow'   => $d->format('D'),
                    'title' => "Renewal due — {$name}",
                    'meta'  => 'Auth ends ' . $d->format('M j') . ' · auto-queued',
                ];
            });

        // Always show the month-end billing run.
        $eom = $now->copy()->endOfMonth();
        $items[] = [
            'day'   => $eom->format('j'),
            'dow'   => $eom->format('D'),
            'title' => 'Month-end billing run',
            'meta'  => 'Auto-builds ' . $eom->format('M j') . ' · review then send',
        ];

        return array_slice($items, 0, 4);
    }

    private function buildRecentActivity(?int $orgId): array
    {
        $colors = ['#2563eb', '#0f172a', '#0f172a', '#16a34a', '#7c3aed'];

        return ActivityLog::with('user')->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest()->limit(6)->get()
            ->values()
            ->map(function ($log, $i) use ($colors) {
                [$title, $desc] = $this->describeActivity($log);
                return [
                    'color' => $colors[$i % count($colors)],
                    'title' => $title,
                    'desc'  => $desc,
                    'who'   => $log->user?->name ?? 'System',
                    'ago'   => $log->created_at?->diffForHumans() ?? '',
                ];
            })->all();
    }

    private function describeActivity(ActivityLog $log): array
    {
        $action  = $log->action ?? '';
        $subject = class_basename($log->subject_type ?? '');

        // Try to resolve a human name for the subject.
        $name = null;
        if ($log->subject_type && class_exists($log->subject_type)) {
            $record = $log->subject_type::query()->withoutGlobalScopes()->find($log->subject_id);
            if ($record) {
                $name = trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? ''))
                    ?: ($record->invoice_number ?? $record->name ?? null);
            }
        }
        $name = $name ?: '#' . $log->subject_id;

        return match (true) {
            str_contains($action, 'Created') && $subject === 'Client'   => ['New client intake', "{$name}'s record was added with demographics, contact info, and coverage profile for review."],
            str_contains($action, 'Updated') && $subject === 'Client'   => ['Client record updated', "{$name}'s profile was reviewed and updated with the latest details."],
            str_contains($action, 'Created') && $subject === 'Intake'   => ['New intake captured', "{$name} was added to the intake pipeline for the office to call and qualify."],
            str_contains($action, 'Created') && $subject === 'Billing'  => ['Billing finalized', "Invoice {$name} was prepared for reimbursement with reviewed hours and authorization matching."],
            str_contains($action, 'Schedule')                          => ['Visit activity', "A visit record for {$name} was {$this->verb($action)}."],
            default                                                     => [trim("{$subject} " . $this->verb($action)) ?: 'Activity', $log->description ?: "{$name} was {$this->verb($action)}."],
        };
    }

    private function verb(string $action): string
    {
        return match (true) {
            str_contains($action, 'Created') => 'created',
            str_contains($action, 'Updated') => 'updated',
            str_contains($action, 'Deleted') => 'removed',
            default => 'changed',
        };
    }

    private function money($amount, bool $abbrev = false): string
    {
        $amount = (float) $amount;
        if ($abbrev) {
            if (abs($amount) >= 1000000) return '$' . rtrim(rtrim(number_format($amount / 1000000, 1), '0'), '.') . 'M';
            if (abs($amount) >= 1000)    return '$' . round($amount / 1000) . 'K';
            return '$' . number_format($amount);
        }
        return '$' . number_format($amount, 2);
    }
}
