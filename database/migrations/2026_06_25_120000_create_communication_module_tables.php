<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('channel'); // email, fax, sms, internal
            $table->string('subject')->nullable();
            $table->text('body');
            $table->text('description')->nullable();
            $table->string('recipient_strategy'); // manual, client_pcp, client_case_coordinator, employee, custom_contact
            $table->string('default_recipient')->nullable();
            $table->json('allowed_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('related');
            $table->foreignId('template_id')->nullable()->constrained('communication_templates')->nullOnDelete();
            $table->string('channel');
            $table->string('direction');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('recipient');
            $table->string('recipient_name')->nullable();
            $table->text('recipient_email')->nullable();
            $table->text('recipient_phone')->nullable();
            $table->text('recipient_fax')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'channel', 'status']);
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('communication_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_id')->constrained('communications')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('disk')->default('local');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });

        Schema::create('secure_message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->nullableMorphs('related');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'last_message_at']);
        });

        Schema::create('secure_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('secure_message_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('secure_message_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('secure_message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('muted_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['thread_id', 'user_id']);
        });

        Schema::create('communication_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->nullableMorphs('related');
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_notifications');
        Schema::dropIfExists('secure_message_participants');
        Schema::dropIfExists('secure_messages');
        Schema::dropIfExists('secure_message_threads');
        Schema::dropIfExists('communication_attachments');
        Schema::dropIfExists('communications');
        Schema::dropIfExists('communication_templates');
    }
};
