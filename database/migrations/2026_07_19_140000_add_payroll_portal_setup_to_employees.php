<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll P4 — manual "Set up in payroll portal ✓" checkoff on the caregiver
 * profile. Replaces the dropped AccountantsWorld auto-sync: an AI agent or staff
 * creates the caregiver in the external payroll portal (filing status + direct
 * deposit), then stamps this checkoff (who + when).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'payroll_portal_setup_at')) {
                $table->timestamp('payroll_portal_setup_at')->nullable();
            }
            if (! Schema::hasColumn('employees', 'payroll_portal_setup_by')) {
                $table->unsignedBigInteger('payroll_portal_setup_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach (['payroll_portal_setup_at', 'payroll_portal_setup_by'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
