<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('role_description')->nullable();
            $table->string('icon', 16)->default('🤖');
            $table->string('icon_bg', 40)->default('bg-[#dbeafe]');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->boolean('on_watch')->default(false);
            $table->string('autonomy_mode', 40)->default('approval_required');
            $table->json('guardrails')->nullable();
            $table->json('action_autonomy')->nullable();
            $table->json('permission_slugs')->nullable();
            $table->json('scope_programs')->nullable();
            $table->json('scope_client_ids')->nullable();
            $table->json('scope_location_ids')->nullable();
            $table->json('credential_keys')->nullable();
            $table->json('catalog')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
