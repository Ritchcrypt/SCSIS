<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (! Schema::hasColumn('notifications', 'type')) {
            return;
        }

        DB::statement("
            ALTER TABLE `notifications`
            MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'announcement'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (! Schema::hasColumn('notifications', 'type')) {
            return;
        }

        DB::statement("
            ALTER TABLE `notifications`
            MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'announcement'
        ");
    }
};