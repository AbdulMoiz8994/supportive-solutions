<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();      // who tapped "Call"
            $table->unsignedBigInteger('employee_id')->nullable()->index();  // caregiver
            $table->unsignedBigInteger('client_id')->nullable()->index();    // who was called
            $table->string('client_name')->nullable();                       // denormalised for history
            $table->string('direction')->default('outbound');
            $table->string('mode')->default('manual');                       // 'ringout' | 'manual'
            $table->string('status')->default('manual')->index();            // 'initiated' | 'manual'
            $table->string('provider')->nullable();                          // 'ringcentral'
            $table->string('provider_call_id')->nullable()->index();
            $table->string('to_number')->nullable();
            $table->string('from_number')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
