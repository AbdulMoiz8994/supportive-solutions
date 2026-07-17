<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->date('effective_date');
            $table->date('last_service_date')->nullable();
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamps();
        });

        // Seed the full client status set if not already present
        $clientStatuses = [
            ['name' => 'Pending',    'color' => 'gray'],
            ['name' => 'Active',     'color' => 'green'],
            ['name' => 'On Hold',    'color' => 'amber'],
            ['name' => 'Recovery',   'color' => 'blue'],
            ['name' => 'Discharged', 'color' => 'red'],
            ['name' => 'Deceased',   'color' => 'gray'],
            ['name' => 'Denied',     'color' => 'red'],
        ];

        foreach ($clientStatuses as $s) {
            \App\Models\Status::updateOrCreate(
                ['name' => $s['name'], 'entity_type' => 'Client'],
                ['color' => $s['color']]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_status_histories');
    }
};
