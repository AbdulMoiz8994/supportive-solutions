<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scan-first intake wizard (client review D1): document scan snapshot,
 * eligibility check result and the recommended program captured during
 * intake so the convert-to-client step can carry them forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intakes', function (Blueprint $table) {
            $table->string('member_id')->nullable()->after('email');
            $table->string('address')->nullable()->after('member_id');
            $table->string('mco_name')->nullable()->after('address');
            $table->json('scan_data')->nullable()->after('scan_id');
            $table->string('eligibility_status')->nullable()->after('scan_data');
            $table->string('eligibility_note')->nullable()->after('eligibility_status');
            $table->dateTime('eligibility_checked_at')->nullable()->after('eligibility_note');
            $table->string('recommended_program')->nullable()->after('eligibility_checked_at');
            $table->foreignId('coverage_type_id')->nullable()->after('recommended_program')
                ->constrained('coverage_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('intakes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coverage_type_id');
            $table->dropColumn([
                'member_id', 'address', 'mco_name', 'scan_data',
                'eligibility_status', 'eligibility_note', 'eligibility_checked_at',
                'recommended_program',
            ]);
        });
    }
};
