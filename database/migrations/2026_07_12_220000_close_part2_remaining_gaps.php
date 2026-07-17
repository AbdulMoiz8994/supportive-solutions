<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('form_submissions', 'signing_token')) {
                $table->string('signing_token', 64)->nullable()->unique()->after('void_reason');
            }
            if (! Schema::hasColumn('form_submissions', 'signature_image')) {
                $table->longText('signature_image')->nullable()->after('signed_by_name');
            }
            if (! Schema::hasColumn('form_submissions', 'esign_sent_at')) {
                $table->timestamp('esign_sent_at')->nullable()->after('signing_token');
            }
            if (! Schema::hasColumn('form_submissions', 'esign_channel')) {
                $table->string('esign_channel', 40)->nullable()->after('esign_sent_at');
            }
            if (! Schema::hasColumn('form_submissions', 'esign_external_id')) {
                $table->string('esign_external_id')->nullable()->after('esign_channel');
            }
        });

        Schema::table('care_details', function (Blueprint $table) {
            if (! Schema::hasColumn('care_details', 'units_used')) {
                $table->unsignedInteger('units_used')->default(0)->after('total_units');
            }
        });

        if (! Schema::hasTable('data_exploration_export_logs')) {
            Schema::create('data_exploration_export_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('dataset', 60);
                $table->string('format', 10);
                $table->unsignedInteger('row_count')->default(0);
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('data_exploration_views', function (Blueprint $table) {
            if (! Schema::hasColumn('data_exploration_views', 'schedule_frequency')) {
                $table->string('schedule_frequency', 20)->nullable()->after('config');
            }
            if (! Schema::hasColumn('data_exploration_views', 'last_emailed_at')) {
                $table->timestamp('last_emailed_at')->nullable()->after('schedule_frequency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('data_exploration_views', function (Blueprint $table) {
            foreach (['schedule_frequency', 'last_emailed_at'] as $column) {
                if (Schema::hasColumn('data_exploration_views', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('data_exploration_export_logs');

        Schema::table('care_details', function (Blueprint $table) {
            if (Schema::hasColumn('care_details', 'units_used')) {
                $table->dropColumn('units_used');
            }
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            foreach (['signing_token', 'signature_image', 'esign_sent_at', 'esign_channel', 'esign_external_id'] as $column) {
                if (Schema::hasColumn('form_submissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
