<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('gender', 50)->nullable()->after('county');
            $table->string('preferred_language', 50)->nullable()->after('gender');
            $table->string('requires_translator', 10)->nullable()->after('preferred_language');
            $table->string('mco_name', 100)->nullable()->after('requires_translator');
            $table->text('medical_conditions')->nullable()->after('mco_name');
            $table->string('medicare_id', 30)->nullable()->after('medical_conditions');
            $table->string('health_plan_id', 50)->nullable()->after('medicare_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'preferred_language',
                'requires_translator',
                'mco_name',
                'medical_conditions',
                'medicare_id',
                'health_plan_id',
            ]);
        });
    }
};
