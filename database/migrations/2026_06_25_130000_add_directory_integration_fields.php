<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('integration_slug')->nullable()->after('is_active');
            $table->string('integration_credential_key')->nullable()->after('integration_slug');
            $table->string('data_flow')->nullable()->after('integration_credential_key');
            $table->string('app_area')->nullable()->after('data_flow');
            $table->string('owning_agent')->nullable()->after('app_area');
        });

        Schema::create('integration_connection_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('not_configured');
            $table->text('message')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_batch_at')->nullable();
            $table->unsignedInteger('errors_30d')->default(0);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->unique('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connection_health');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'integration_slug',
                'integration_credential_key',
                'data_flow',
                'app_area',
                'owning_agent',
            ]);
        });
    }
};
