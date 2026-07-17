<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_claim_audits', function (Blueprint $table) {
            $table->foreignId('care_detail_id')->nullable()->after('employee_id')->constrained('care_details')->nullOnDelete();

            $table->string('coverage_type')->nullable()->after('program_type');
            $table->string('payer_name')->nullable()->after('health_plan_name');
            $table->string('billing_method', 40)->nullable()->after('submission_channel');
            $table->string('billing_route', 40)->nullable()->after('billing_method');
            $table->string('invoice_number')->nullable()->after('claim_number');

            $table->date('authorization_start_date')->nullable()->after('authorization_number');
            $table->string('authorization_status', 30)->nullable()->index()->after('authorization_valid_through');
            $table->string('authorization_document_path')->nullable()->after('authorization_status');

            $table->unsignedSmallInteger('unit_minutes')->default(15)->after('units');
            $table->decimal('approved_monthly_hours', 8, 2)->nullable()->after('unit_minutes');
            $table->decimal('approved_weekly_hours', 8, 2)->nullable()->after('approved_monthly_hours');
            $table->decimal('calculated_daily_hours', 8, 2)->nullable()->after('approved_weekly_hours');
            $table->decimal('calculated_approved_hours', 8, 2)->nullable()->after('calculated_daily_hours');

            $table->decimal('scheduled_hours', 8, 2)->nullable()->after('total_hours');
            $table->decimal('verified_hours', 8, 2)->nullable()->after('scheduled_hours');
            $table->decimal('completed_visit_hours', 8, 2)->nullable()->after('verified_hours');
            $table->string('evv_status', 30)->nullable()->default('not_connected')->after('evv_exempt');
            $table->string('visit_verification_status', 30)->nullable()->after('evv_status');
            $table->boolean('clock_in_verified')->default(false)->after('visit_verification_status');
            $table->boolean('clock_out_verified')->default(false)->after('clock_in_verified');

            $table->string('billing_status', 30)->nullable()->index()->after('claim_status');
            $table->decimal('expected_amount', 12, 2)->nullable()->after('total_amount');
            $table->decimal('balance_amount', 12, 2)->nullable()->after('paid_amount');
            $table->string('payment_status', 30)->nullable()->index()->after('balance_amount');
            $table->decimal('adjustment_amount', 12, 2)->nullable()->after('payment_status');
            $table->decimal('denial_amount', 12, 2)->nullable()->after('adjustment_amount');
            $table->text('adjustment_reason')->nullable()->after('rejection_reason');
            $table->string('payer_reference')->nullable()->after('adjustment_reason');
            $table->string('eob_document_path')->nullable()->after('pdf_path');
            $table->date('payment_date')->nullable()->index()->after('paid_at');

            $table->string('ai_extraction_status', 30)->default('not_connected')->after('documents');
            $table->decimal('ai_extracted_amount', 12, 2)->nullable()->after('ai_extraction_status');
            $table->decimal('ai_extracted_confidence', 5, 2)->nullable()->after('ai_extracted_amount');
            $table->boolean('ai_review_required')->default(false)->after('ai_extracted_confidence');
            $table->text('ai_notes')->nullable()->after('ai_review_required');

            $table->text('override_reason')->nullable()->after('notes');
            $table->foreignId('overridden_by')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable()->after('overridden_by');

            $table->json('issue_flags')->nullable()->after('overridden_at');
            $table->string('last_action')->nullable()->after('issue_flags');
            $table->json('activity_log')->nullable()->after('last_action');

            $table->index(['organization_id', 'billing_status'], 'bca_org_billing_status_idx');
            $table->index(['organization_id', 'authorization_status'], 'bca_org_auth_status_idx');
            $table->index(['organization_id', 'coverage_type'], 'bca_org_coverage_idx');
        });
    }

    public function down(): void
    {
        Schema::table('billing_claim_audits', function (Blueprint $table) {
            $table->dropForeign(['care_detail_id']);
            $table->dropForeign(['overridden_by']);
            $table->dropIndex('bca_org_billing_status_idx');
            $table->dropIndex('bca_org_auth_status_idx');
            $table->dropIndex('bca_org_coverage_idx');

            $table->dropColumn([
                'care_detail_id', 'coverage_type', 'payer_name', 'billing_method', 'billing_route', 'invoice_number',
                'authorization_start_date', 'authorization_status', 'authorization_document_path',
                'unit_minutes', 'approved_monthly_hours', 'approved_weekly_hours', 'calculated_daily_hours', 'calculated_approved_hours',
                'scheduled_hours', 'verified_hours', 'completed_visit_hours', 'evv_status', 'visit_verification_status',
                'clock_in_verified', 'clock_out_verified', 'billing_status', 'expected_amount', 'balance_amount',
                'payment_status', 'adjustment_amount', 'denial_amount', 'adjustment_reason', 'payer_reference',
                'eob_document_path', 'payment_date', 'ai_extraction_status', 'ai_extracted_amount', 'ai_extracted_confidence',
                'ai_review_required', 'ai_notes', 'override_reason', 'overridden_by', 'overridden_at',
                'issue_flags', 'last_action', 'activity_log',
            ]);
        });
    }
};
