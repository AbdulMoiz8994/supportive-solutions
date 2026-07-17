<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_claim_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->string('claim_number')->index();
            $table->string('program_type', 20)->index(); // MICH, DHS
            $table->date('billing_period')->index();
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('total_hours', 8, 2)->nullable();
            $table->unsignedSmallInteger('total_days')->nullable();
            $table->unsignedTinyInteger('days_required_per_week')->nullable();
            $table->string('days_met_status')->nullable();
            $table->string('service_code', 20)->nullable();
            $table->string('service_description')->nullable();
            $table->unsignedInteger('units')->nullable();

            $table->decimal('hourly_rate', 8, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->nullable();

            $table->string('submission_channel');
            $table->string('channel_subtext')->nullable();
            $table->string('payer_type')->nullable();
            $table->string('health_plan_name')->nullable();
            $table->string('medicaid_id')->nullable();
            $table->string('plan_member_id')->nullable();
            $table->string('authorization_number')->nullable();
            $table->date('authorization_valid_through')->nullable();
            $table->string('authorization_description')->nullable();
            $table->string('authorizing_worker_name')->nullable();
            $table->string('caregiver_relationship')->nullable();
            $table->boolean('evv_exempt')->default(false);

            $table->string('claim_status', 30)->index();
            $table->string('status_detail')->nullable();
            $table->string('hold_reason')->nullable();
            $table->string('audit_status', 30)->default('not_reviewed')->index();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();

            $table->string('pdf_path')->nullable();
            $table->json('lifecycle_events')->nullable();
            $table->json('documents')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'claim_number']);
            $table->index(['organization_id', 'billing_period', 'claim_status'], 'bca_org_period_status_idx');
            $table->index(['organization_id', 'program_type'], 'bca_org_program_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_claim_audits');
    }
};
