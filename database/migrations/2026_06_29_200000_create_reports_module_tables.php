<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_report_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->string('source', 60)->default('clients');
            $table->json('columns')->nullable();
            $table->json('filters')->nullable();
            $table->string('group_by', 60)->nullable();
            $table->string('schedule_frequency', 20)->nullable();
            $table->json('schedule_recipients')->nullable();
            $table->text('prompt')->nullable();
            $table->timestamps();
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_slug', 80);
            $table->foreignId('custom_report_id')->nullable()->constrained('custom_report_definitions')->nullOnDelete();
            $table->string('frequency', 20);
            $table->string('format', 10)->default('csv');
            $table->json('recipients')->nullable();
            $table->json('filters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_slug', 80);
            $table->foreignId('custom_report_id')->nullable()->constrained('custom_report_definitions')->nullOnDelete();
            $table->foreignId('report_schedule_id')->nullable()->constrained('report_schedules')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 20)->nullable();
            $table->string('format', 10)->default('csv');
            $table->string('status', 20)->default('completed');
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['report_slug', 'organization_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('custom_report_definitions');
    }
};
