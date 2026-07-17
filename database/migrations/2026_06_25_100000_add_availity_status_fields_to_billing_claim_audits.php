<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_claim_audits', function (Blueprint $table) {
            $table->string('availity_reference_id')->nullable()->after('payer_reference');
            $table->string('availity_status')->nullable()->after('availity_reference_id');
            $table->json('availity_status_payload')->nullable()->after('availity_status');
            $table->timestamp('availity_status_checked_at')->nullable()->after('availity_status_payload');
        });
    }

    public function down(): void
    {
        Schema::table('billing_claim_audits', function (Blueprint $table) {
            $table->dropColumn([
                'availity_reference_id',
                'availity_status',
                'availity_status_payload',
                'availity_status_checked_at',
            ]);
        });
    }
};
