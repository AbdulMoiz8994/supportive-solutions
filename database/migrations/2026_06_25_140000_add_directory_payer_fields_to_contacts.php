<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('claim_channel')->nullable()->after('provider_id');
            $table->decimal('contracted_rate', 8, 2)->nullable()->after('claim_channel');
            $table->foreignId('parent_contact_id')->nullable()->after('contracted_rate')->constrained('contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['parent_contact_id']);
            $table->dropColumn(['claim_channel', 'contracted_rate', 'parent_contact_id']);
        });
    }
};
