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
        Schema::table('intakes', function (Blueprint $table) {
            $table->date('id_expiry')->nullable()->after('dob');
            $table->date('champs_association_date')->nullable()->after('id_expiry');
            $table->string('scan_id')->nullable()->after('champs_association_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intakes', function (Blueprint $table) {
            $table->dropColumn(['id_expiry', 'champs_association_date', 'scan_id']);
        });
    }
};
