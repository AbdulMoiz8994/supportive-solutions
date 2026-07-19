<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual payroll: record that a human or AI agent has processed this
     * caregiver's pay in the external payroll portal (who + when).
     */
    public function up(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            $table->timestamp('processed_payroll_at')->nullable()->after('exported_at');
            $table->foreignId('processed_by')->nullable()->after('processed_payroll_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn('processed_payroll_at');
        });
    }
};
