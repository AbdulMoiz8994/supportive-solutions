<?php

namespace App\Support;

class ClientRegistryStatus
{
    public static function normalize(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        return match (true) {
            $value === '' || $value === 'active' => 'active',
            str_contains($value, 'hold') => 'on_hold',
            $value === 'discharged' => 'discharged',
            str_contains($value, 'recovery') => 'recovery',
            str_contains($value, 'pending') => 'pending',
            default => $value,
        };
    }

    /**
     * @param  iterable<int, array{status_key?: string, status?: string, program?: string}>  $rows
     * @return array<string, int>
     */
    public static function tabCounts(iterable $rows): array
    {
        $counts = [
            'all' => 0,
            'active' => 0,
            'pending_dhs' => 0,
            'pending_mich' => 0,
            'recovery' => 0,
            'on_hold' => 0,
            'discharged' => 0,
        ];

        foreach ($rows as $row) {
            $counts['all']++;
            self::incrementTab($counts, self::tabKey($row));
        }

        return $counts;
    }

    /**
     * @param  array{status_key?: string, status?: string, program?: string}  $row
     */
    public static function tabKey(array $row): string
    {
        $status = $row['status_key'] ?? self::normalize($row['status'] ?? '');
        $program = $row['program'] ?? '—';

        return match (true) {
            $status === 'active' => 'active',
            $status === 'on_hold' => 'on_hold',
            $status === 'discharged' => 'discharged',
            $status === 'recovery' => 'recovery',
            $status === 'pending' && $program === 'DHS' => 'pending_dhs',
            $status === 'pending' && $program === 'MICH' => 'pending_mich',
            default => 'all',
        };
    }

    /**
     * @param  array{status_key?: string, status?: string, program?: string}  $row
     */
    public static function matchesTab(array $row, string $tab): bool
    {
        if ($tab === 'all') {
            return true;
        }

        return self::tabKey($row) === $tab;
    }

    /**
     * @param  array<string, int>  $counts
     */
    protected static function incrementTab(array &$counts, string $tab): void
    {
        if ($tab !== 'all' && array_key_exists($tab, $counts)) {
            $counts[$tab]++;
        }
    }
}
