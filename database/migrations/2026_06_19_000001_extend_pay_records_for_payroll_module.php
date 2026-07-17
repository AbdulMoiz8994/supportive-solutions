<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('period_key', 7); // 2026-05
            $table->date('build_date');
            $table->date('pay_date');
            $table->unsignedInteger('record_count')->default(0);
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->foreignId('built_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('built_at')->nullable();
            $table->string('status')->default('built'); // built, synced
            $table->timestamps();
        });

        Schema::create('payroll_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('pay_record_id')->nullable()->constrained('pay_records')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('value_before')->nullable();
            $table->string('value_after')->nullable();
            $table->text('detail')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->timestamps();
        });

        Schema::table('pay_records', function (Blueprint $table) {
            $table->string('period_key', 7)->nullable()->index()->after('period');
            $table->string('hours_source')->nullable()->after('hours');
            $table->date('grace_end_date')->nullable()->after('status');
            $table->foreignId('compliance_form_id')->nullable()->after('client_id')->constrained('compliance_forms')->nullOnDelete();
            $table->string('hold_reason')->nullable()->after('grace_end_date');
            $table->foreignId('batch_id')->nullable()->after('hold_reason')->constrained('payroll_batches')->nullOnDelete();
            $table->json('lifecycle_events')->nullable()->after('stub_path');
            $table->string('caregiver_type')->nullable()->after('lifecycle_events'); // family, agency
            $table->timestamp('verified_at')->nullable()->after('caregiver_type');
            $table->string('program_tag')->nullable()->after('verified_at'); // MICH, DHS
        });
    }

    public function down(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            $table->dropForeign(['compliance_form_id']);
            $table->dropForeign(['batch_id']);
            $table->dropColumn([
                'period_key', 'hours_source', 'grace_end_date', 'compliance_form_id',
                'hold_reason', 'batch_id', 'lifecycle_events', 'caregiver_type',
                'verified_at', 'program_tag',
            ]);
        });

        Schema::dropIfExists('payroll_audit_logs');
        Schema::dropIfExists('payroll_batches');
    }
};
