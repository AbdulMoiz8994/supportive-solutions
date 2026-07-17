<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D1 scan-first intake: program-specific path (DHS Time/Task vs MICH/ICO/DAAA PA),
 * optional caregiver assignment, and multi-document scan snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intakes', function (Blueprint $table) {
            $table->string('program_track', 20)->nullable()->after('recommended_program');
            $table->decimal('hours_per_week', 8, 2)->nullable()->after('program_track');
            $table->unsignedInteger('pa_units')->nullable()->after('hours_per_week');
            $table->foreignId('assigned_employee_id')->nullable()->after('pa_units')
                ->constrained('employees')->nullOnDelete();
            $table->json('scanned_documents')->nullable()->after('scan_data');
        });
    }

    public function down(): void
    {
        Schema::table('intakes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_employee_id');
            $table->dropColumn(['program_track', 'hours_per_week', 'pa_units', 'scanned_documents']);
        });
    }
};
