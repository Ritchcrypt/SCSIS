<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')
                    ->unique()
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->foreignId('barangay_id')
                    ->nullable()
                    ->constrained('barangays')
                    ->nullOnDelete();

                $table->enum('employee_type', ['official', 'tanod', 'admin', 'personnel'])
                    ->default('personnel');

                $table->string('position', 100)->nullable();
                $table->string('department', 100)->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};