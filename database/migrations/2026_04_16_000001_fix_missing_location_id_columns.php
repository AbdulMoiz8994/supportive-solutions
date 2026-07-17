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
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'location_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Try to place it after organization_id if it exists
                    if (Schema::hasColumn($tableName, 'organization_id')) {
                        $table->foreignId('location_id')->nullable()->after('organization_id')->constrained('locations')->nullOnDelete();
                    } else {
                        $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                    }
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
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'location_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['location_id']);
                    $table->dropColumn('location_id');
                });
            }
        }
    }
};
