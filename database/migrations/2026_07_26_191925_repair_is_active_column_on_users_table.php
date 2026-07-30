<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the missing account activation column safely.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_active')
                    ->default(true)
                    ->after('status');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Preserve existing account statuses
        |--------------------------------------------------------------------------
        |
        | Existing active users remain active.
        | Existing inactive users remain inactive.
        |
        */

        if (
            Schema::hasColumn('users', 'status')
            && Schema::hasColumn('users', 'is_active')
        ) {
            DB::table('users')
                ->where('status', false)
                ->update([
                    'is_active' => false,
                ]);

            DB::table('users')
                ->where('status', true)
                ->update([
                    'is_active' => true,
                ]);

            DB::table('users')
                ->whereNull('status')
                ->update([
                    'is_active' => true,
                ]);
        }
    }

    /**
     * Remove only the column added by this migration.
     */
    public function down(): void
    {
        if (
            Schema::hasTable('users')
            && Schema::hasColumn('users', 'is_active')
        ) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};