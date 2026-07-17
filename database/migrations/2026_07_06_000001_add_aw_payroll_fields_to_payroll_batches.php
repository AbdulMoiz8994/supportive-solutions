<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->unsignedInteger('aw_pay_schedule_id')->nullable()->after('status');
            $table->unsignedInteger('aw_payroll_id')->nullable()->after('aw_pay_schedule_id');
            $table->text('aw_sync_error')->nullable()->after('aw_payroll_id');
            $table->timestamp('aw_synced_at')->nullable()->after('aw_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropColumn([
                'aw_pay_schedule_id',
                'aw_payroll_id',
                'aw_sync_error',
                'aw_synced_at',
            ]);
        });
    }
};
