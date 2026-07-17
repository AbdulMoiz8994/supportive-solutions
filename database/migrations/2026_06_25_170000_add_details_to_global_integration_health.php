<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_integration_health', function (Blueprint $table) {
            $table->unsignedInteger('latency_ms')->nullable()->after('message');
            $table->json('details')->nullable()->after('latency_ms');
        });
    }

    public function down(): void
    {
        Schema::table('global_integration_health', function (Blueprint $table) {
            $table->dropColumn(['latency_ms', 'details']);
        });
    }
};
