<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'home_latitude')) {
                $table->decimal('home_latitude', 10, 7)->nullable()->after('address');
            }
            if (! Schema::hasColumn('clients', 'home_longitude')) {
                $table->decimal('home_longitude', 10, 7)->nullable()->after('home_latitude');
            }
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('form_submissions', 'fields_snapshot')) {
                $table->json('fields_snapshot')->nullable()->after('field_values');
            }
            if (! Schema::hasColumn('form_submissions', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('locked_at');
            }
            if (! Schema::hasColumn('form_submissions', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('form_submissions', 'void_reason')) {
                $table->string('void_reason')->nullable()->after('voided_at');
            }
        });

        if (! Schema::hasTable('task_comments')) {
            Schema::create('task_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');

        Schema::table('form_submissions', function (Blueprint $table) {
            foreach (['fields_snapshot', 'expires_at', 'voided_at', 'void_reason'] as $column) {
                if (Schema::hasColumn('form_submissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            foreach (['home_latitude', 'home_longitude'] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
