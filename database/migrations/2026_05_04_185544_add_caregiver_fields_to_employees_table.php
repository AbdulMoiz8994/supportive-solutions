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
        Schema::table('employees', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
            $table->string('preferred_language')->nullable();
            $table->date('id_expiry_date')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->boolean('is_18_plus')->default(false);
            $table->boolean('is_work_eligible')->default(false);
            $table->boolean('has_background_check')->default(false);
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('scan_id_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'preferred_language',
                'id_expiry_date',
                'city',
                'state',
                'zip_code',
                'is_18_plus',
                'is_work_eligible',
                'has_background_check',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'scan_id_path',
            ]);
        });
    }
};
