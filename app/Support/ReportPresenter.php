<?php

namespace App\Support;

class ReportPresenter
{
    public static function money(float|int|null $amount, bool $abbrev = false): string
    {
        $amount = (float) ($amount ?? 0);

        if ($abbrev) {
            if (abs($amount) >= 1_000_000) {
                return '$'.rtrim(rtrim(number_format($amount / 1_000_000, 1), '0'), '.').'M';
            }
            if (abs($amount) >= 1_000) {
                return '$'.number_format($amount / 1_000, $amount >= 10_000 ? 0 : 1).'K';
            }
        }

        return '$'.number_format($amount, $abbrev ? 0 : 2);
    }

    public static function percent(float|int|null $value, int $decimals = 1): string
    {
        return number_format((float) ($value ?? 0), $decimals).'%';
    }

    public static function number(float|int|null $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals);
    }

    public static function ratio(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return $denominator === 0 && $numerator === 0 ? 100.0 : 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    public static function collectionRatePill(float $rate): string
    {
        if ($rate >= 98) {
            return 'green';
        }
        if ($rate >= 85) {
            return 'amber';
        }

        return 'red';
    }

    public static function scheduleBadgeClass(string $schedule): string
    {
        return match ($schedule) {
            'monthly', 'weekly', 'per_run', 'quarterly' => 'green',
            default => 'grey',
        };
    }
}
