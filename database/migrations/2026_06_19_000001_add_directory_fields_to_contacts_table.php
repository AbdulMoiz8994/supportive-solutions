<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('type');
            $table->string('address_line1')->nullable()->after('provider_id');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city')->nullable()->after('address_line2');
            $table->string('state', 50)->nullable()->after('city');
            $table->string('county')->nullable()->after('state');
            $table->string('zip', 20)->nullable()->after('county');
            $table->text('notes')->nullable()->after('zip');
            $table->boolean('is_active')->default(true)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'job_title',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'county',
                'zip',
                'notes',
                'is_active',
            ]);
        });
    }
};
