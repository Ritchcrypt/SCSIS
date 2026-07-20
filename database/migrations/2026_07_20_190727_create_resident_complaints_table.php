<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_complaints', function (Blueprint $table) {
            $table->id();

            $table->string('complaint_code', 30)->unique();

            $table->foreignId('resident_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('complainant_name');
            $table->string('contact_number')->nullable();

            $table->text('complaint_description');
            $table->text('complaint_address');

            $table->string('evidence_path')->nullable();

            $table->enum('status', [
                'submitted',
                'under_review',
                'in_progress',
                'resolved',
                'rejected',
            ])->default('submitted');

            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index(['resident_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_complaints');
    }
};