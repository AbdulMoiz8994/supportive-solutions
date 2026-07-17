<?php

namespace App\Support\Api;

/**
 * Turns a gross figure into an estimated gross → net paystub breakdown for the
 * mobile app. FICA is statutory (7.65%); federal/state come from configurable
 * estimate rates (default 0). Always flagged `estimated` so the UI can label it
 * — the authoritative net still comes from the payroll provider.
 */
class PayBreakdown
{
    /**
     * @return array{gross: float, federal_tax: float, state_tax: float, fica: float, net: float, estimated: bool}
     */
    public static function forGross(?float $gross): array
    {
        $gross = round((float) $gross, 2);

        $rates = config('payroll.tax_estimate', []);
        $enabled = (bool) ($rates['enabled'] ?? true);

        $fica = $enabled ? round($gross * (float) ($rates['fica'] ?? 0.0765), 2) : 0.0;
        $federal = $enabled ? round($gross * (float) ($rates['federal'] ?? 0.0), 2) : 0.0;
        $state = $enabled ? round($gross * (float) ($rates['state'] ?? 0.0), 2) : 0.0;

        return [
            'gross' => $gross,
            'federal_tax' => $federal,
            'state_tax' => $state,
            'fica' => $fica,
            'net' => round($gross - $federal - $state - $fica, 2),
            'estimated' => $enabled,
        ];
    }
}
