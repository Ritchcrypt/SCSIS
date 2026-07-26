<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the security activation field without deleting existing data.
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
        | Preserve existing account status
        |--------------------------------------------------------------------------
        |
        | Existing users with status = false remain inactive. Existing active
        | users remain active.
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
        }
    }

    /**
     * Reverse only this migration.
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