<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->dateTime('actual_clock_in')->nullable();
            $table->dateTime('actual_clock_out')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->string('status')->default('Scheduled'); // Scheduled, In-Progress, Completed, Missed
            $table->boolean('evv_status')->default(false);
            $table->json('visit_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('Pending'); // Pending, Sent, Paid, Rejected
            $table->string('eob_path')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->morphs('documentable'); // client_id, employee_id, etc.
            $table->string('name');
            $table->string('path');
            $table->string('type')->nullable(); // ID, Medical Form, Signed Application
            $table->string('category')->default('General'); // Medical, Legal, HR, General
            $table->date('expires_at')->nullable();
            $table->string('verification_status')->default('Pending'); // Pending, Verified, Rejected
            $table->boolean('is_signed')->default(false);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('billings');
        Schema::dropIfExists('schedules');
    }
};
