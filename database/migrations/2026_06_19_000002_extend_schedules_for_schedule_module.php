<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('schedules', 'title')) {
                $table->string('title')->nullable()->after('employee_id');
            }
            if (! Schema::hasColumn('schedules', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (! Schema::hasColumn('schedules', 'event_type')) {
                $table->string('event_type')->default('care_visit')->after('description');
            }
            if (! Schema::hasColumn('schedules', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('event_type')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('schedules', 'start_at')) {
                $table->dateTime('start_at')->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('schedules', 'end_at')) {
                $table->dateTime('end_at')->nullable()->after('start_at');
            }
            if (! Schema::hasColumn('schedules', 'timezone')) {
                $table->string('timezone')->nullable()->after('end_at');
            }
            if (! Schema::hasColumn('schedules', 'address')) {
                $table->string('address')->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('schedules', 'all_day')) {
                $table->boolean('all_day')->default(false)->after('address');
            }
            if (! Schema::hasColumn('schedules', 'metadata')) {
                $table->json('metadata')->nullable()->after('all_day');
            }
            if (! Schema::hasColumn('schedules', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $this->relaxAssignmentForeignKeys();

        Schema::table('schedules', function (Blueprint $table) {
            $table->index('event_type');
            $table->index(['organization_id', 'start_at']);
            $table->index(['client_id', 'start_at']);
            $table->index(['employee_id', 'start_at']);
        });

        DB::table('schedules')->orderBy('id')->lazy()->each(function ($row) {
            if ($row->start_at && $row->title) {
                return;
            }

            // Normalise to bare Y-m-d / H:i:s — `date` may itself be a full
            // datetime (e.g. '2026-04-01 00:00:00'); concatenating it raw with a
            // time produced an unparseable "double time" value that 500s on cast.
            $date = $row->date ? date('Y-m-d', strtotime($row->date)) : null;
            if (! $date) {
                return; // no date to anchor the event — skip backfill
            }
            $startTime = $row->start_time ? date('H:i:s', strtotime($row->start_time)) : '00:00:00';
            $endTime = $row->end_time ? date('H:i:s', strtotime($row->end_time)) : $startTime;

            $startAt = $date.' '.$startTime;
            $endAt = $date.' '.$endTime;

            if ($startTime && $endTime && $endTime < $startTime) {
                $endAt = date('Y-m-d', strtotime($date.' +1 day')).' '.$endTime;
            }

            $title = $row->title;
            if (! $title) {
                $client = DB::table('clients')->where('id', $row->client_id)->first();
                $title = $client
                    ? trim(($client->first_name ?? '').' '.($client->last_name ?? '')).' Visit'
                    : 'Schedule Event';
            }

            DB::table('schedules')->where('id', $row->id)->update([
                'title' => $title,
                'event_type' => $row->event_type ?: 'care_visit',
                'start_at' => $startAt,
                'end_at' => $endAt,
                'timezone' => $row->timezone ?: config('app.timezone', 'UTC'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'start_at']);
            $table->dropIndex(['client_id', 'start_at']);
            $table->dropIndex(['employee_id', 'start_at']);
            $table->dropIndex(['event_type']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'created_by')) {
                $table->dropForeign(['created_by']);
            }
            $table->dropSoftDeletes();
            $table->dropColumn([
                'title',
                'description',
                'event_type',
                'created_by',
                'start_at',
                'end_at',
                'timezone',
                'address',
                'all_day',
                'metadata',
            ]);
        });
    }

    private function relaxAssignmentForeignKeys(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['employee_id']);
        });

        DB::statement('ALTER TABLE schedules MODIFY client_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE schedules MODIFY employee_id BIGINT UNSIGNED NULL');

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }
};
