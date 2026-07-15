<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tanod_profiles') && Schema::hasColumn('tanod_profiles', 'badge_number')) {
            DB::statement('ALTER TABLE tanod_profiles MODIFY badge_number VARCHAR(50) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tanod_profiles') && Schema::hasColumn('tanod_profiles', 'badge_number')) {
            DB::statement('ALTER TABLE tanod_profiles MODIFY badge_number VARCHAR(50) NOT NULL');
        }
    }
};