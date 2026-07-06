<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('case_records')) {
            return;
        }

        Schema::create('case_records', function (Blueprint $table) {
            $table->id();

            $table->string('case_number', 50)->unique();
            $table->string('case_type', 50);
            $table->string('subject_name', 255);
            $table->string('contact', 50)->nullable();
            $table->string('address', 500)->nullable();

            $table->foreignId('incident_id')
                ->nullable()
                ->constrained('incidents')
                ->nullOnDelete();

            $table->string('incident_title', 255)->nullable();

            $table->string('status', 50)->default('open');
            $table->date('hearing_date')->nullable();
            $table->string('handled_by', 255)->nullable();

            $table->text('resolution')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_records');
    }
};
