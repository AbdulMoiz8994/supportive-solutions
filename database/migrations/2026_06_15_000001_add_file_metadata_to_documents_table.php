<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('disk')->default('local')->after('path');
            $table->string('mime_type')->nullable()->after('disk');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->string('original_filename')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['disk', 'mime_type', 'file_size', 'original_filename']);
        });
    }
};
