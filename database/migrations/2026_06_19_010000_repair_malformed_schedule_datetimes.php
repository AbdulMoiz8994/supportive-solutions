<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repair schedules whose start_at/end_at were backfilled by concatenating a
     * `date` that already carried a time component — e.g. the unparseable
     * '2026-04-01 00:00:00 08:00:00'. Such values throw on datetime cast and 500
     * every page that renders them (client profile, schedule index, calendar).
     * Rebuild them from the date + start_time/end_time columns.
     *
     * Idempotent: rows already in clean 'Y-m-d H:i:s' form are skipped.
     */
    public function up(): void
    {
        DB::table('schedules')->orderBy('id')->lazy()->each(function ($row) {
            $fixes = [];

            foreach (['start_at' => 'start_time', 'end_at' => 'end_time'] as $atColumn => $timeColumn) {
                $value = $row->$atColumn;

                if ($value === null) {
                    continue;
                }

                // A clean SQL datetime is exactly 'Y-m-d H:i:s'. Anything else is malformed.
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $value)) {
                    continue;
                }

                $date = $row->date ? date('Y-m-d', strtotime($row->date)) : null;
                $time = $row->$timeColumn ? date('H:i:s', strtotime($row->$timeColumn)) : '00:00:00';

                if ($date) {
                    $fixes[$atColumn] = $date.' '.$time;
                } else {
                    // Fall back to the first parseable datetime fragment.
                    $timestamp = strtotime(substr((string) $value, 0, 19));
                    $fixes[$atColumn] = $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
                }
            }

            if ($fixes) {
                DB::table('schedules')->where('id', $row->id)->update($fixes);
            }
        });
    }

    public function down(): void
    {
        // Data repair only — nothing to roll back.
    }
};
