<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('emergency_agency_logs')) {
            return;
        }

        Schema::create('emergency_agency_logs', function (Blueprint $table) {
            $table->id();

            $table->string('agency', 50);
            $table->string('agency_name', 100);
            $table->string('hotline', 30)->nullable();

            $table->text('message')->nullable();

            $table->foreignId('incident_id')
                ->nullable()
                ->constrained('incidents')
                ->nullOnDelete();

            $table->string('status', 50)->default('pending');

            $table->foreignId('initiated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('notified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_agency_logs');
    }
};