<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->text('metadata')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->json('metadata')->nullable()->change();
        });
    }
};
