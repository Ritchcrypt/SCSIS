<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert the notifications.type column to VARCHAR on MySQL/MariaDB.
     *
     * SQLite does not support MySQL's ALTER TABLE ... MODIFY syntax.
     * The conversion is unnecessary for the temporary SQLite test database,
     * so SQLite safely skips this migration operation.
     */
    public function up(): void
    {
        if (
            ! Schema::hasTable('notifications')
            || ! Schema::hasColumn('notifications', 'type')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("
                ALTER TABLE `notifications`
                MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'announcement'
            ");
        }
    }

    /**
     * A destructive rollback to the former ENUM is intentionally avoided.
     *
     * New notification types may already exist. Converting them back to a
     * limited ENUM could truncate or reject valid production data.
     */
    public function down(): void
    {
        //
    }
};