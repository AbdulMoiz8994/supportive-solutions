<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Care-task checklist items attached to a scheduled visit. These drive the
 * mobile "Care Tasks" list on the active shift, the "Confirm Completed Tasks"
 * step at clock-out, and the home-screen "Task Done" progress ring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('label');
            $table->string('category')->nullable(); // Personal Care, Homemaking, ...
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable(); // employee id
            $table->timestamps();

            $table->index(['schedule_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_tasks');
    }
};
