<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('main_phone', 30)->nullable()->after('legal_address_zip');
            $table->string('efax_number', 30)->nullable()->after('main_phone');
            $table->string('service_state', 2)->nullable()->default('MI')->after('efax_number');
        });

        Schema::create('caregiver_activation_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('status', 20)->default('pending');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_activation_codes');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['main_phone', 'efax_number', 'service_state']);
        });
    }
};
