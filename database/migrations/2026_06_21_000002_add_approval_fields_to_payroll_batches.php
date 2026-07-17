<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            // Approval workflow: built → pending_approval → approved → exported
            $table->string('approval_status')->default('pending_approval')->after('status');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('approved_at');
            // Track if accountant notification was sent
            $table->timestamp('accountant_notified_at')->nullable()->after('approval_note');
        });

        // Existing 'built' rows get pending_approval so they show up in the queue
        \Illuminate\Support\Facades\DB::table('payroll_batches')
            ->where('approval_status', 'pending_approval')
            ->update(['approval_status' => 'pending_approval']);
    }

    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approval_status', 'approved_by', 'approved_at', 'approval_note', 'accountant_notified_at']);
        });
    }
};
