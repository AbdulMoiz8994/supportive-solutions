<?php

namespace App\Services\Intake;

use App\Models\CoverageType;
use App\Models\Intake;
use App\Services\Availity\AvailityEligibilityService;
use Carbon\Carbon;

/**
 * Scan-first intake (client review D1): eligibility screen + program
 * recommendation. Offline format checks run first; when Availity credentials
 * exist a live coverage inquiry (270/271-style) confirms active Medicaid.
 */
class IntakeEligibilityService
{
    public function __construct(protected AvailityEligibilityService $availityEligibility) {}

    /**
     * @param  array{dob?: ?string, member_id?: ?string, mco_name?: ?string}  $data
     * @return array{status: string, note: string, checked_at: string, live: bool, payer_name: ?string, plan_name: ?string}
     */
    public function check(array $data): array
    {
        $memberId = trim((string) ($data['member_id'] ?? ''));
        $dob = $this->parseDate($data['dob'] ?? null);

        $status = Intake::ELIGIBILITY_NEEDS_VERIFICATION;
        $notes = [];
        $live = false;
        $payerName = null;
        $planName = null;

        if ($dob && $dob->age < 18) {
            return [
                'status' => Intake::ELIGIBILITY_INELIGIBLE,
                'note' => 'Applicant is under 18 — adult home-help programs do not apply.',
                'checked_at' => now()->toIso8601String(),
                'live' => false,
                'payer_name' => null,
                'plan_name' => null,
            ];
        }

        if ($memberId === '') {
            $notes[] = 'No Medicaid ID provided — collect the ID to verify coverage.';
        } elseif (! preg_match('/^MD-\d{5}$/i', $memberId)) {
            $notes[] = 'Medicaid ID format looks unusual (expected MD-00000) — double-check the card.';
        } else {
            $status = Intake::ELIGIBILITY_ELIGIBLE;
            $notes[] = 'Medicaid ID format valid.';
        }

        if (! $dob) {
            $status = Intake::ELIGIBILITY_NEEDS_VERIFICATION;
            $notes[] = 'Date of birth missing — needed for the payer eligibility inquiry.';
        }

        if ($memberId !== '' && $dob && $this->availityEligibility->isConfigured()) {
            $offlineStatus = $status;
            $inquiry = $this->availityEligibility->inquire($memberId, $dob);
            $live = $inquiry['live'];
            $payerName = $inquiry['payer_name'];
            $planName = $inquiry['plan_name'];
            $notes[] = $inquiry['message'];

            if ($inquiry['active'] === true) {
                $status = Intake::ELIGIBILITY_ELIGIBLE;
            } elseif ($inquiry['active'] === false) {
                $status = Intake::ELIGIBILITY_INELIGIBLE;
            } elseif ($offlineStatus === Intake::ELIGIBILITY_ELIGIBLE) {
                // Live payer could not confirm, but offline format checks passed.
                $status = Intake::ELIGIBILITY_ELIGIBLE;
            } else {
                $status = Intake::ELIGIBILITY_NEEDS_VERIFICATION;
            }
        } elseif ($status === Intake::ELIGIBILITY_ELIGIBLE) {
            $notes[] = 'Live payer verification runs when Availity credentials are configured.';
        }

        return [
            'status' => $status,
            'note' => implode(' ', $notes),
            'checked_at' => now()->toIso8601String(),
            'live' => $live,
            'payer_name' => $payerName,
            'plan_name' => $planName,
        ];
    }

    /**
     * @param  array{dob?: ?string, mco_name?: ?string, plan_name?: ?string, payer_name?: ?string}  $data
     * @return array{program: string, program_track: string, coverage_type_id: ?int, reason: string, billing_mode: string}
     */
    public function recommendProgram(array $data): array
    {
        $mco = trim((string) ($data['mco_name'] ?? ''));
        $planName = strtolower((string) ($data['plan_name'] ?? ''));
        $payerName = strtolower((string) ($data['payer_name'] ?? ''));

        if ($mco === '') {
            return [
                'program' => 'DHS Home Help',
                'program_track' => 'dhs',
                'coverage_type_id' => $this->coverageTypeId('DHS'),
                'reason' => 'Straight Medicaid with no MCO plan — routes to DHS Home Help (Time/Task, no PA).',
                'billing_mode' => 'time_task',
            ];
        }

        if (str_contains($planName, 'ico') || str_contains($payerName, 'ico')) {
            return [
                'program' => 'ICO',
                'program_track' => 'ico',
                'coverage_type_id' => $this->coverageTypeId('ICO'),
                'reason' => 'Integrated care plan detected — ICO path with MCO prior auth.',
                'billing_mode' => 'prior_auth',
            ];
        }

        if (str_contains($planName, 'aaa') || str_contains($planName, 'daaa') || str_contains($payerName, 'aaa')) {
            return [
                'program' => 'DAAA',
                'program_track' => 'daaa',
                'coverage_type_id' => $this->coverageTypeId('DAAA'),
                'reason' => 'Area Agency on Aging plan detected — DAAA path with MCO prior auth.',
                'billing_mode' => 'prior_auth',
            ];
        }

        return [
            'program' => 'MICH',
            'program_track' => 'mich',
            'coverage_type_id' => $this->coverageTypeId('MICH'),
            'reason' => 'Enrolled with '.$mco.' — managed-care MICH path (PA + units via Availity).',
            'billing_mode' => 'prior_auth',
        ];
    }

    protected function coverageTypeId(string $program): ?int
    {
        $types = CoverageType::query()->get(['id', 'name']);

        return match ($program) {
            'DHS' => $types->first(fn ($t) => stripos($t->name, 'DHS') !== false || stripos($t->name, 'Home Help') !== false)?->id,
            'ICO' => $types->first(fn ($t) => stripos($t->name, 'ICO') !== false)?->id,
            'DAAA' => $types->first(fn ($t) => stripos($t->name, 'DAAA') !== false || stripos($t->name, 'AAA') !== false)?->id,
            default => $types->first(fn ($t) => stripos($t->name, 'MICH') !== false || (stripos($t->name, 'DHS') === false && stripos($t->name, 'Home Help') === false && stripos($t->name, 'ICO') === false))?->id,
        };
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
