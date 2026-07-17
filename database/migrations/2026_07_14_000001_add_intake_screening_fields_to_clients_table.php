<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intake & Screening tab (client review): the Referral, Eligibility Screening,
 * Services Requested and Initial Notes panels rendered editable fields but had
 * nowhere to save to. Give them real columns so the panels can persist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Referral
            $table->string('referral_source')->nullable()->after('onboarding_steps');
            $table->date('referral_received_date')->nullable()->after('referral_source');
            $table->string('referred_by')->nullable()->after('referral_received_date');
            $table->string('currently_receiving_care')->nullable()->after('referred_by');
            $table->string('intake_taken_by')->nullable()->after('currently_receiving_care');
            $table->date('intake_date')->nullable()->after('intake_taken_by');
            // Eligibility Screening
            $table->date('eligibility_verified_date')->nullable()->after('intake_date');
            $table->string('eligibility_result')->nullable()->after('eligibility_verified_date');
            // Services Requested (list of ADLs) + Initial Notes
            $table->json('services_requested')->nullable()->after('eligibility_result');
            $table->text('initial_notes')->nullable()->after('services_requested');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'referral_source',
                'referral_received_date',
                'referred_by',
                'currently_receiving_care',
                'intake_taken_by',
                'intake_date',
                'eligibility_verified_date',
                'eligibility_result',
                'services_requested',
                'initial_notes',
            ]);
        });
    }
};
