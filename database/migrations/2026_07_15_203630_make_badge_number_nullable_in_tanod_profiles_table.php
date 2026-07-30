<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('tanod_profiles')
            || ! Schema::hasColumn('tanod_profiles', 'badge_number')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        /*
        |--------------------------------------------------------------------------
        | MySQL / MariaDB only
        |--------------------------------------------------------------------------
        |
        | SQLite does not support ALTER TABLE ... MODIFY.
        | SQLite testing safely skips this operation.
        |
        */

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE `tanod_profiles`
                 MODIFY `badge_number` VARCHAR(50) NULL'
            );
        }
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('tanod_profiles')
            || ! Schema::hasColumn('tanod_profiles', 'badge_number')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        /*
        |--------------------------------------------------------------------------
        | Restore the previous non-nullable column on rollback
        |--------------------------------------------------------------------------
        */

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE `tanod_profiles`
                 MODIFY `badge_number` VARCHAR(50) NOT NULL'
            );
        }
    }
};