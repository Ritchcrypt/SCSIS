<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tanod_tasks') && ! Schema::hasColumn('tanod_tasks', 'incident_id')) {
            Schema::table('tanod_tasks', function (Blueprint $table) {
                $table->foreignId('incident_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('incidents')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tanod_tasks') && Schema::hasColumn('tanod_tasks', 'incident_id')) {
            Schema::table('tanod_tasks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('incident_id');
            });
        }
    }
};