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
        $tables = [
            'clients',
            'employees',
            'schedules',
            'contacts',
            'intakes',
            'messages',
            'activity_logs',
            'client_requests'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'clients',
            'employees',
            'schedules',
            'contacts',
            'intakes',
            'messages',
            'activity_logs',
            'client_requests'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign([$tableName . '_location_id_foreign']);
                    $table->dropColumn('location_id');
                });
            }
        }
    }
};
