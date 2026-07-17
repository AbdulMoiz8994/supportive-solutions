<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('agency_npi', 20)->nullable()->after('status');
            $table->string('tax_id_ein', 20)->nullable()->after('agency_npi');
            $table->string('medicaid_provider_id', 30)->nullable()->after('tax_id_ein');
            $table->string('legal_business_name')->nullable()->after('medicaid_provider_id');
            $table->string('legal_address_street')->nullable()->after('legal_business_name');
            $table->string('legal_address_city')->nullable()->after('legal_address_street');
            $table->string('legal_address_state', 2)->nullable()->after('legal_address_city');
            $table->string('legal_address_zip', 10)->nullable()->after('legal_address_state');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'agency_npi',
                'tax_id_ein',
                'medicaid_provider_id',
                'legal_business_name',
                'legal_address_street',
                'legal_address_city',
                'legal_address_state',
                'legal_address_zip',
            ]);
        });
    }
};
