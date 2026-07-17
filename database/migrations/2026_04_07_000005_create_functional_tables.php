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
        Schema::create('intakes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('dob')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('source')->nullable(); // Facebook, Meta, Google, etc.
            $table->string('status')->default('New');
            $table->integer('status_id')->nullable(); // linked to statuses
            $table->text('notes')->nullable();
            $table->integer('converted_client_id')->nullable();
            $table->timestamps();
        });

        Schema::create('care_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('billing_code')->default('T019');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('total_units')->nullable();
            $table->decimal('hours_per_week', 8, 2)->nullable();
            $table->string('status')->default('Active'); // Active, Pending Renewal
            $table->string('authorized_by')->nullable(); // Case coordinator name
            $table->timestamps();
        });

        Schema::create('client_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_employee');
        Schema::dropIfExists('care_details');
        Schema::dropIfExists('intakes');
    }
};
