<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('verified_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('exported_at')->nullable()->after('locked_by');
        });
    }

    public function down(): void
    {
        Schema::table('pay_records', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['locked_at', 'locked_by', 'exported_at']);
        });
    }
};
