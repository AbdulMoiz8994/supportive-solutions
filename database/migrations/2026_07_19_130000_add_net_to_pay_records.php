<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Net pay captured from the pay stub (gross already exists). Manual payroll:
     * the stub is the source of truth for pay date + gross + net.
     */
    public function up(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            $table->decimal('net', 10, 2)->nullable()->after('gross');
        });
    }

    public function down(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            $table->dropColumn('net');
        });
    }
};
