<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            // Structured triage fields promoted out of the metadata JSON blob for
            // reliable querying, dashboarding, and SLA reporting.
            $table->string('ai_triage_category')->nullable()->after('metadata');   // billing | scheduling | wellness | clinical | concern | general
            $table->string('ai_triage_priority')->nullable()->after('ai_triage_category');  // normal | urgent
            $table->boolean('concern_flagged')->default(false)->after('ai_triage_priority');

            $table->index(['organization_id', 'concern_flagged']);
            $table->index(['organization_id', 'ai_triage_category']);
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'concern_flagged']);
            $table->dropIndex(['organization_id', 'ai_triage_category']);
            $table->dropColumn(['ai_triage_category', 'ai_triage_priority', 'concern_flagged']);
        });
    }
};
