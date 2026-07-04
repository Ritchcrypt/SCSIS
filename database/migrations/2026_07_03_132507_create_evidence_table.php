<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evidence')) {
            Schema::create('evidence', function (Blueprint $table) {
                $table->id();

                $table->foreignId('incident_id')
                    ->constrained('incidents')
                    ->cascadeOnDelete();

                $table->foreignId('uploaded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('file_name', 255);
                $table->string('file_path', 255);
                $table->string('file_type', 50)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();

                $table->timestamp('uploaded_at')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence');
    }
};