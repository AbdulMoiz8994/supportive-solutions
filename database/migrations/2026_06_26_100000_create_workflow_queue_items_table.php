<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('queue_type', 32); // approval, human_task, exception
            $table->string('slug', 120);
            $table->string('status', 32)->default('pending'); // pending, approved, held, rejected, completed, dismissed
            $table->json('meta')->nullable();
            $table->nullableMorphs('subject');
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'queue_type', 'slug']);
            $table->index(['organization_id', 'queue_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_queue_items');
    }
};
