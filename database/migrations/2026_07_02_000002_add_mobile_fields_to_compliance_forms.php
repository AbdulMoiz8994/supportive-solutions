<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the mobile monthly-certification flow writes: the caregiver's yes/no
 * certification answers, an optional free-text note, and the captured
 * signature image path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_forms', function (Blueprint $table) {
            $table->json('certification')->nullable()->after('days');
            $table->text('additional_notes')->nullable()->after('certification');
            $table->string('signature_path')->nullable()->after('additional_notes');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_forms', function (Blueprint $table) {
            $table->dropColumn(['certification', 'additional_notes', 'signature_path']);
        });
    }
};
