<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->foreignId('request_template_id')->nullable()->after('client_id')->constrained('request_templates')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->after('request_template_id')->constrained('users')->nullOnDelete();
            $table->string('delivery_method')->nullable()->after('method');
            $table->string('recipient_type')->nullable()->after('delivery_method');
            $table->string('recipient_email')->nullable()->after('recipient_type');
            $table->string('recipient_fax')->nullable()->after('recipient_email');
            $table->string('subject')->nullable()->after('recipient_fax');
            $table->longText('body_snapshot')->nullable()->after('subject');
        });
    }

    public function down(): void
    {
        Schema::table('client_requests', function (Blueprint $table) {
            $table->dropForeign(['request_template_id']);
            $table->dropForeign(['sent_by']);
            $table->dropColumn([
                'request_template_id',
                'sent_by',
                'delivery_method',
                'recipient_type',
                'recipient_email',
                'recipient_fax',
                'subject',
                'body_snapshot',
            ]);
        });
    }
};
