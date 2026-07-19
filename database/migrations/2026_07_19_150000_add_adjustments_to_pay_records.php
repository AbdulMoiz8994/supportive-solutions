<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll P5 — corrections / reversals / supplemental pay. Allows MORE THAN ONE
 * pay record per caregiver per period (added anytime, out of order):
 *   - record_type: 'regular' | 'supplemental' | 'reversal'
 *   - supplemental: a new payment on an already-run period (backdated/missed
 *     case or underpayment) — carries service_dates + adjustment_reason + own stub.
 *   - reversal: a clawback of an overpayment — recovery_amount + recovery_status
 *     (Requested → Recovered) + adjustment_reason.
 *   - parent_pay_record_id links the adjustment back to the original run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            if (! Schema::hasColumn('pay_records', 'record_type')) {
                $table->string('record_type')->default('regular');
            }
            if (! Schema::hasColumn('pay_records', 'adjustment_reason')) {
                $table->text('adjustment_reason')->nullable();
            }
            if (! Schema::hasColumn('pay_records', 'service_dates')) {
                $table->string('service_dates')->nullable();
            }
            if (! Schema::hasColumn('pay_records', 'recovery_amount')) {
                $table->decimal('recovery_amount', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('pay_records', 'recovery_status')) {
                $table->string('recovery_status')->nullable();
            }
            if (! Schema::hasColumn('pay_records', 'parent_pay_record_id')) {
                $table->unsignedBigInteger('parent_pay_record_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            foreach ([
                'record_type', 'adjustment_reason', 'service_dates',
                'recovery_amount', 'recovery_status', 'parent_pay_record_id',
            ] as $col) {
                if (Schema::hasColumn('pay_records', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
