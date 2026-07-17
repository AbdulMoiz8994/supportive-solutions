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
        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('schedules', 'clock_in_latitude')) {
                $table->decimal('clock_in_latitude', 10, 8)->after('actual_clock_in')->nullable();
                $table->decimal('clock_in_longitude', 11, 8)->after('clock_in_latitude')->nullable();
                $table->decimal('clock_out_latitude', 10, 8)->after('actual_clock_out')->nullable();
                $table->decimal('clock_out_longitude', 11, 8)->after('clock_out_latitude')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['clock_in_latitude', 'clock_in_longitude', 'clock_out_latitude', 'clock_out_longitude']);
        });
    }
};
