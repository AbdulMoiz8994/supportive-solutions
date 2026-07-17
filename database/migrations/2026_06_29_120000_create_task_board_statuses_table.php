<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_board_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32);
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('header_bg', 16)->default('#f8fbff');
            $table->string('badge_bg', 16)->default('#f1f5f9');
            $table->string('badge_text', 16)->default('#475569');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
            $table->index(['organization_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_board_statuses');
    }
};
