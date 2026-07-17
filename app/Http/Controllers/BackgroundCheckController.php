<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Carbon\Carbon;

/**
 * Agency-wide Background Checks page (Tab 7). One matrix of all four checks —
 * CHAMPS, ICHAT, SAM.gov, OIG LEIE — across every caregiver, with an overall
 * status. Flags route to "verify same-person" rather than auto-disqualify.
 * Rolls up from each caregiver's Background Checks tab (BackgroundCheck model).
 * SAM + OIG are free/public; ICHAT auto-running needs the agency's MSP account.
 */
class BackgroundCheckController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Employee::class);

        $caregivers = Employee::where('position', 'Caregiver')
            ->with('backgroundChecks')
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        $rows = $caregivers->map(fn (Employee $c) => $this->buildRow($c))->values()->all();

        $samClear = collect($rows)->whereIn('sam_tone', ['g'])->count();
        $oigClear = collect($rows)->whereIn('oig_tone', ['g'])->count();

        $kpis = [
            'monitored'  => count($rows),
            'ichat_due'  => collect($rows)->where('ichat_due', true)->count(),
            'flags'      => collect($rows)->where('status_key', 'flagged')->count(),
            'batch_clear'=> min($samClear, $oigClear),
            'onboarding' => collect($rows)->where('status_key', 'onboarding')->count(),
            'all_clear'  => collect($rows)->where('status_key', 'clear')->count(),
        ];

        return view('pages.background-checks.index', [
            'title' => 'Background Checks',
            'rows'  => $rows,
            'kpis'  => $kpis,
        ]);
    }

    /** @return array<string, mixed> */
    protected function buildRow(Employee $c): array
    {
        $checks = $c->backgroundChecks->keyBy('type');

        $champs = $this->champsCell($checks->get('CHAMPS'));
        $ichat  = $this->ichatCell($checks->get('ICHAT'));
        $sam    = $this->simpleCell($checks->get('SAM'));
        $oig    = $this->simpleCell($checks->get('OIG'));

        // Overall status
        $flagged = in_array('r', [$champs['tone'], $ichat['tone'], $sam['tone'], $oig['tone']], true)
            && ($champs['flag'] || $ichat['flag'] || $sam['flag'] || $oig['flag']);

        $onboarding = $champs['pending'] || $ichat['pending'] || $checks->isEmpty();

        if ($flagged) {
            $statusKey = 'flagged'; $statusLabel = 'Verify (On Hold)'; $statusTone = 'red';
        } elseif ($onboarding) {
            $statusKey = 'onboarding'; $statusLabel = 'In onboarding'; $statusTone = 'amber';
        } elseif ($ichat['due']) {
            $statusKey = 'ichat_due'; $statusLabel = 'ICHAT due'; $statusTone = 'amber';
        } else {
            $statusKey = 'clear'; $statusLabel = 'All clear'; $statusTone = 'green';
        }

        return [
            'id'       => $c->id,
            'name'     => $c->name,
            'initials' => strtoupper(mb_substr($c->first_name ?: 'C', 0, 1).mb_substr($c->last_name ?: '', 0, 1)),
            'champs_label' => $champs['label'], 'champs_tone' => $champs['tone'],
            'ichat_label'  => $ichat['label'],  'ichat_tone'  => $ichat['tone'],  'ichat_sub' => $ichat['sub'],
            'sam_label'    => $sam['label'],    'sam_tone'    => $sam['tone'],    'sam_sub' => $sam['sub'],
            'oig_label'    => $oig['label'],    'oig_tone'    => $oig['tone'],    'oig_sub' => $oig['sub'],
            'status_label' => $statusLabel, 'status_tone' => $statusTone, 'status_key' => $statusKey,
            'ichat_due'    => $ichat['due'],
        ];
    }

    /** @return array{label:string,tone:string,flag:bool,pending:bool} */
    protected function champsCell($check): array
    {
        if (! $check) {
            return ['label' => 'Enrolling', 'tone' => 'a', 'flag' => false, 'pending' => true];
        }
        $status = (string) ($check->status ?? '');
        if ($check->provider_id || in_array($status, ['Clear', 'Approved', 'Associated', 'Assoc.'], true)) {
            return ['label' => $check->provider_id ? 'Assoc.' : ($status ?: 'Approved'), 'tone' => 'g', 'flag' => false, 'pending' => false];
        }
        if ($status === 'Flagged') {
            return ['label' => 'Flag', 'tone' => 'r', 'flag' => true, 'pending' => false];
        }

        return ['label' => $status ?: 'Enrolling', 'tone' => 'a', 'flag' => false, 'pending' => true];
    }

    /** @return array{label:string,tone:string,sub:string,flag:bool,pending:bool,due:bool} */
    protected function ichatCell($check): array
    {
        if (! $check) {
            return ['label' => 'Submitted', 'tone' => 'a', 'sub' => '', 'flag' => false, 'pending' => true, 'due' => false];
        }
        if ((string) $check->status === 'Flagged') {
            return ['label' => 'Flag', 'tone' => 'r', 'sub' => 'possible match', 'flag' => true, 'pending' => false, 'due' => false];
        }
        if ($check->next_due) {
            $days = (int) now()->startOfDay()->diffInDays($check->next_due->copy()->startOfDay(), false);
            if ($days < 0) {
                return ['label' => 'Overdue', 'tone' => 'r', 'sub' => 'renew now', 'flag' => false, 'pending' => false, 'due' => true];
            }
            if ($days <= 30) {
                return ['label' => 'Due '.$days.'d', 'tone' => 'a', 'sub' => 'renew '.$check->next_due->format('M j'), 'flag' => false, 'pending' => false, 'due' => true];
            }

            return ['label' => $check->next_due->format('M Y'), 'tone' => 'g', 'sub' => '', 'flag' => false, 'pending' => false, 'due' => false];
        }

        return ['label' => $check->status ?: 'Clear', 'tone' => 'g', 'sub' => '', 'flag' => false, 'pending' => false, 'due' => false];
    }

    /** SAM / OIG monthly lists. @return array{label:string,tone:string,sub:string,flag:bool} */
    protected function simpleCell($check): array
    {
        if (! $check) {
            return ['label' => '—', 'tone' => 'x', 'sub' => '', 'flag' => false];
        }
        if ((string) $check->status === 'Flagged') {
            return ['label' => 'Flag', 'tone' => 'r', 'sub' => 'possible match', 'flag' => true];
        }
        $when = $check->last_run ? $check->last_run->format('M j') : ($check->status ?: 'Clear');

        return ['label' => $when, 'tone' => 'g', 'sub' => '', 'flag' => false];
    }
}
