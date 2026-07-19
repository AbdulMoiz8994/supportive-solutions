<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing manual operation — the manual claim flow replaces the dropped
 * clearinghouse/Availity auto-submit. Adds an explicit "Eligibility verified"
 * checkoff (its own step, before submit) and a "submitted by" stamp for the
 * manual "Mark submitted" step (submitted_at already exists). Every step is
 * still who+when stamped; nothing here changes an amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_claim_audits', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_claim_audits', 'eligibility_verified_at')) {
                $table->timestamp('eligibility_verified_at')->nullable();
            }
            if (! Schema::hasColumn('billing_claim_audits', 'eligibility_verified_by')) {
                $table->unsignedBigInteger('eligibility_verified_by')->nullable();
            }
            if (! Schema::hasColumn('billing_claim_audits', 'eligibility_note')) {
                $table->string('eligibility_note')->nullable();
            }
            if (! Schema::hasColumn('billing_claim_audits', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_claim_audits', function (Blueprint $table) {
            foreach (['eligibility_verified_at', 'eligibility_verified_by', 'eligibility_note', 'submitted_by'] as $col) {
                if (Schema::hasColumn('billing_claim_audits', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
