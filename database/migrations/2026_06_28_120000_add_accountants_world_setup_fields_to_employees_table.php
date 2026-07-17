<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('aw_employee_id')->nullable()->after('payroll_system');
            $table->string('aw_setup_status', 32)->nullable()->after('aw_employee_id');
            $table->text('aw_setup_error')->nullable()->after('aw_setup_status');
            $table->text('aw_setup_payload')->nullable()->after('aw_setup_error');
            $table->timestamp('aw_setup_attempted_at')->nullable()->after('aw_setup_payload');
        });

        DB::table('employees')
            ->where('payroll_system', 'AccountantsWorld')
            ->whereNull('aw_employee_id')
            ->whereNull('aw_setup_status')
            ->update([
                'aw_setup_status' => 'failed',
                'aw_setup_error' => 'Previous attempt did not confirm sync — retry or mark as added manually.',
                'aw_setup_attempted_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'aw_employee_id',
                'aw_setup_status',
                'aw_setup_error',
                'aw_setup_payload',
                'aw_setup_attempted_at',
            ]);
        });
    }
};
