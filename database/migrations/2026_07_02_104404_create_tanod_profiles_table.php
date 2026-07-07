<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tanod_profiles')) {
            Schema::create('tanod_profiles', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')
                    ->nullable()
                    ->unique()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('employee_id')
                    ->nullable()
                    ->unique()
                    ->constrained('employees')
                    ->nullOnDelete();

                $table->string('badge_number', 50)->unique();
                $table->string('contact_number', 50)->nullable();
                $table->string('purok_assignment', 100)->nullable();
                $table->date('date_appointed')->nullable();

                $table->string('shift', 50)->default('day');
                $table->string('status', 50)->default('active');

                $table->text('notes')->nullable();

                $table->timestamps();
            });

            return;
        }

        Schema::table('tanod_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('tanod_profiles', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->unique()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tanod_profiles', 'employee_id')) {
                $table->foreignId('employee_id')
                    ->nullable()
                    ->unique()
                    ->after('user_id')
                    ->constrained('employees')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tanod_profiles', 'badge_number')) {
                $table->string('badge_number', 50)->unique()->after('employee_id');
            }

            if (! Schema::hasColumn('tanod_profiles', 'contact_number')) {
                $table->string('contact_number', 50)->nullable()->after('badge_number');
            }

            if (! Schema::hasColumn('tanod_profiles', 'purok_assignment')) {
                $table->string('purok_assignment', 100)->nullable()->after('contact_number');
            }

            if (! Schema::hasColumn('tanod_profiles', 'date_appointed')) {
                $table->date('date_appointed')->nullable()->after('purok_assignment');
            }

            if (! Schema::hasColumn('tanod_profiles', 'shift')) {
                $table->string('shift', 50)->default('day')->after('date_appointed');
            }

            if (! Schema::hasColumn('tanod_profiles', 'status')) {
                $table->string('status', 50)->default('active')->after('shift');
            }

            if (! Schema::hasColumn('tanod_profiles', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanod_profiles');
    }
};