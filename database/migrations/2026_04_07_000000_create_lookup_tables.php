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
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('entity_type'); // Client, Employee, Intake
            $table->string('color')->nullable();
            $table->integer('delay_notification_days')->default(0);
            $table->timestamps();
        });

        Schema::create('coverage_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Medicaid, Medicare, etc.
            $table->string('plan_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value_payload')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('coverage_types');
        Schema::dropIfExists('settings');
    }
};
