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
        // 1. Locations Table
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('state')->default('Michigan');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Roles Table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // 3. Permissions Table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module')->nullable(); // e.g., 'Clients', 'Staff', 'Billing'
            $table->timestamps();
        });

        // 4. Permission Role Pivot
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // 5. Location User Pivot (Many-to-Many)
        Schema::create('location_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // 6. Update Users Table
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('invite_token')->nullable()->after('remember_token');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
            $table->timestamp('two_factor_verified_at')->nullable()->after('invite_expires_at');
            $table->string('two_factor_code')->nullable()->after('two_factor_verified_at');
            $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 
                'is_active', 
                'invite_token', 
                'invite_expires_at', 
                'two_factor_verified_at',
                'two_factor_code',
                'two_factor_expires_at'
            ]);
        });

        Schema::dropIfExists('location_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('locations');
    }
};
