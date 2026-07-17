<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('coordinator_id')->nullable(); // contact id
            $table->string('template'); // POC, Auth, Notes
            $table->string('method'); // Email, Fax
            $table->text('notes')->nullable();
            $table->string('status')->default('Sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_requests');
    }
};
