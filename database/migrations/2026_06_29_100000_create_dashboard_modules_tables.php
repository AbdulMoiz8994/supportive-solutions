<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('todo');
            $table->string('priority', 16)->default('medium');
            $table->date('due_date')->nullable();
            $table->string('assignee_type', 16)->default('user');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('related');
            $table->string('source', 32)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('target_type', 16)->default('client');
            $table->json('fields')->nullable();
            $table->boolean('requires_signature')->default(true);
            $table->boolean('is_compliance_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_template_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 32)->default('draft');
            $table->json('field_values')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('data_exploration_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('dataset', 64);
            $table->json('config');
            $table->timestamps();

            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exploration_views');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_templates');
        Schema::dropIfExists('tasks');
    }
};
