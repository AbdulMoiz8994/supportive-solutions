<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('aw_setup_http_status')->nullable()->after('aw_setup_error');
            $table->string('aw_setup_error_context', 32)->nullable()->after('aw_setup_http_status');
        });

        DB::table('employees')
            ->where('aw_setup_status', 'failed')
            ->where('aw_setup_error', 'Previous attempt did not confirm sync — retry or mark as added manually.')
            ->update([
                'aw_setup_error' => null,
                'aw_setup_error_context' => 'legacy',
            ]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['aw_setup_http_status', 'aw_setup_error_context']);
        });
    }
};
