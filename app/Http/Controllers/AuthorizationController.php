<?php

namespace App\Http\Controllers;

use App\Models\CareDetail;
use App\Models\Client;
use Carbon\Carbon;

/**
 * Agency-wide Authorizations page (Tab 5). One list of every client's
 * authorization — MICH Prior Auths (which expire) and DHS Time/Task Sheets
 * (which don't, but need a 6-month reassessment). Each row links back to the
 * client's Program & Authorization tab. Data comes from the CareDetail model;
 * "units used / remaining" is left for the EVV (HHAeXchange) integration.
 */
class AuthorizationController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Client::class);

        // Location/org scoped like the rest of the app (respects the location switcher).
        $clients = Client::with(['careDetails', 'coverageType', 'contacts'])->get();

        $rows = [];

        foreach ($clients as $client) {
            $program = $client->program_label; // 'DHS' | 'MICH' | '—'
            $isDhs = $program === 'DHS';
            $coordinator = optional($client->caseCoordinator())->name;

            foreach ($client->careDetails as $auth) {
                $rows[] = $this->buildRow($client, $auth, $program, $isDhs, $coordinator);
            }
        }

        // Soonest-first: most urgent (expired/expiring) at the top.
        usort($rows, fn ($a, $b) => ($a['sort_key'] <=> $b['sort_key']));

        $kpis = [
            'active'        => collect($rows)->where('status_key', 'active')->count(),
            'expiring_21'   => collect($rows)->where('expiring_21', true)->count(),
            'expired_hold'  => collect($rows)->where('status_key', 'expired')->count(),
            'renewals'      => collect($rows)->where('status_key', 'renewal')->count(),
            'dhs_reassess'  => collect($rows)->where('reassess_60', true)->count(),
            'mich'          => collect($rows)->where('program', 'MICH')->count(),
            'dhs'           => collect($rows)->where('program', 'DHS')->count(),
            'total'         => count($rows),
        ];

        // For the "Log authorization" picker: every client you can log a PA against
        // (including clients that have none yet), so the button is never a dead end.
        $clientOptions = $clients
            ->map(fn (Client $c) => [
                'id'   => $c->id,
                'name' => trim($c->first_name.' '.$c->last_name) ?: 'Client #'.$c->id,
            ])
            ->sortBy('name')
            ->values();

        return view('pages.authorizations.index', [
            'title' => 'Authorizations',
            'rows'  => $rows,
            'kpis'  => $kpis,
            'clientOptions' => $clientOptions,
        ]);
    }

    /** @return array<string, mixed> */
    protected function buildRow(Client $client, CareDetail $auth, string $program, bool $isDhs, ?string $coordinator): array
    {
        $name = trim($client->first_name.' '.$client->last_name) ?: 'Client #'.$client->id;
        $initials = strtoupper(mb_substr($client->first_name ?: 'C', 0, 1).mb_substr($client->last_name ?: '', 0, 1));
        $year = $auth->start_date?->format('Y') ?? now()->format('Y');
        $ref = $isDhs ? 'TT-'.$year.'-'.str_pad((string) $auth->id, 2, '0', STR_PAD_LEFT)
                      : 'PA-'.$year.'-'.str_pad((string) $auth->id, 4, '0', STR_PAD_LEFT);

        // Units / hours column
        if ($isDhs) {
            $hours = $auth->hours_per_month;
            $unitsLabel = $hours ? rtrim(rtrim(number_format($hours, 1), '0'), '.').' hrs/mo' : '—';
        } else {
            $code = $auth->billing_code ?: 'T1019';
            $unitsLabel = $auth->total_units ? $code.' · '.$auth->total_units.'/mo' : $code;
        }

        $days = $auth->days_until_expiry; // signed; null if no end date
        $reassessDate = $auth->end_date ?? ($auth->start_date?->copy()->addMonths(6));
        $daysToReassess = $reassessDate
            ? (int) now()->startOfDay()->diffInDays($reassessDate->copy()->startOfDay(), false)
            : null;
        $reassessDue = $daysToReassess !== null && $daysToReassess <= 0;
        $reassessSoon = $daysToReassess !== null && ! $reassessDue && $daysToReassess <= 60;

        if ($isDhs) {
            // DHS: no expiry — show the reassessment cadence, never "expired".
            $expiresLabel = $reassessDate ? 'Reassess '.$reassessDate->format('M j') : 'No expiry';
            $expiresSub = '· no expiry';
            $expiring21 = false;
            $reassess60 = $reassessDue || $reassessSoon;

            if ($reassessDue) {
                $expiresTone = 'amber';
                $statusKey = 'reassess_due';
                $statusLabel = 'Reassessment due';
                $statusTone = 'amber';
                $sortKey = $daysToReassess; // overdue first, alongside urgent MICH rows
            } else {
                $expiresTone = $reassessSoon ? 'amber' : 'green';
                $statusKey = 'active';
                $statusLabel = 'Active';
                $statusTone = 'green';
                $sortKey = $reassessSoon ? $daysToReassess : ($reassessDate ? $reassessDate->timestamp : PHP_INT_MAX);
            }
        } elseif ($program === '—') {
            // No coverage type on file: we can't know DHS vs MICH rules, so never
            // claim "Prior Auth"/"Expired" — surface it as data to fix instead.
            $expiresLabel = $auth->end_date ? $auth->end_date->format('M j, Y') : 'No end date';
            $expiresTone = 'grey';
            $expiresSub = '';
            $statusKey = 'pending';
            $statusLabel = 'Verify program';
            $statusTone = 'amber';
            $sortKey = PHP_INT_MAX - 2;
            $expiring21 = false;
            $reassess60 = false;
        } else {
            // MICH: real expiry countdown.
            if ($days === null) {
                $expiresLabel = 'No end date';
                $expiresTone = 'grey';
                $statusKey = 'pending';
                $statusLabel = 'Pending';
                $statusTone = 'amber';
                $sortKey = PHP_INT_MAX - 1;
            } elseif ($days < 0) {
                $expiresLabel = 'Expired '.abs($days).'d ago';
                $expiresTone = 'red';
                $statusKey = 'expired';
                $statusLabel = 'On Hold';
                $statusTone = 'red';
                $sortKey = $days; // most negative first
            } elseif ($auth->needs_renewal) {
                $expiresLabel = $days.' days · '.$auth->end_date->format('M j');
                $expiresTone = 'amber';
                $statusKey = 'renewal';
                $statusLabel = $days <= 21 ? 'Renewal queued' : 'Expiring Soon';
                $statusTone = 'amber';
                $sortKey = $days;
            } else {
                $expiresLabel = $days.' days · '.$auth->end_date->format('M j');
                $expiresTone = 'green';
                $statusKey = 'active';
                $statusLabel = 'Active';
                $statusTone = 'green';
                $sortKey = $days;
            }
            $expiresSub = '';
            $expiring21 = $days !== null && $days >= 0 && $days <= 21;
            $reassess60 = false;
        }

        return [
            'id'           => $auth->id,
            'client_id'    => $client->id,
            'name'         => $name,
            'initials'     => $initials,
            'program'      => $program,
            'program_display' => $client->program_display,
            'type'         => $isDhs ? 'Time/Task' : ($program === '—' ? '—' : 'Prior Auth'),
            'mco'          => $isDhs
                ? (optional($client->aswContact())->name ?? '—')
                : ($client->mco_name ?: ($coordinator ?: '—')),
            'auth_ref'     => $ref,
            'units'        => $unitsLabel,
            'expires'      => $expiresLabel,
            'expires_tone' => $expiresTone,
            'expires_sub'  => $expiresSub ?? '',
            'status_label' => $statusLabel,
            'status_tone'  => $statusTone,
            'status_key'   => $statusKey,
            'expiring_21'  => $expiring21,
            'reassess_60'  => $reassess60,
            'sort_key'     => $sortKey,
        ];
    }
}
