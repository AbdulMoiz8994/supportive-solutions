<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // SSN — store only last 4 for display; full SSN encrypted separately
            $table->string('ssn_last4', 4)->nullable()->after('member_id');
            $table->text('ssn_encrypted')->nullable()->after('ssn_last4');

            // Household / Live-In fields
            $table->boolean('lives_with_caregiver')->default(false)->after('ssn_encrypted');
            $table->string('evv_status', 50)->nullable()->after('lives_with_caregiver'); // Exempt / Active

            // Live-in exemption tracking
            $table->string('live_in_exemption_status', 30)->nullable()->after('evv_status'); // Approved/Pending/Not exempt
            $table->date('live_in_exemption_submitted_at')->nullable()->after('live_in_exemption_status');
            $table->date('live_in_exemption_approved_at')->nullable()->after('live_in_exemption_submitted_at');
            $table->date('live_in_exemption_expires_at')->nullable()->after('live_in_exemption_approved_at');

            // Onboarding steps — JSON array, one entry per step with status/date/note
            $table->json('onboarding_steps')->nullable()->after('live_in_exemption_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'ssn_last4',
                'ssn_encrypted',
                'lives_with_caregiver',
                'evv_status',
                'live_in_exemption_status',
                'live_in_exemption_submitted_at',
                'live_in_exemption_approved_at',
                'live_in_exemption_expires_at',
                'onboarding_steps',
            ]);
        });
    }
};
