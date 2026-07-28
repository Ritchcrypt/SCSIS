<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Required table and column checks
        |--------------------------------------------------------------------------
        */

        if (
            ! Schema::hasTable('tanod_profiles')
            || ! Schema::hasTable('employees')
            || ! Schema::hasColumn('tanod_profiles', 'employee_id')
            || ! Schema::hasColumn('employees', 'id')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        /*
        |--------------------------------------------------------------------------
        | SQLite compatibility
        |--------------------------------------------------------------------------
        |
        | SQLite cannot reliably add this foreign key to an existing table.
        | MySQL/MariaDB is the production and active test database.
        |
        */

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $databaseName = DB::connection()->getDatabaseName();
        $constraintName = 'tanod_profiles_employee_id_foreign';

        /*
        |--------------------------------------------------------------------------
        | Avoid adding the same constraint twice
        |--------------------------------------------------------------------------
        */

        $constraintAlreadyExists = DB::table(
            'information_schema.TABLE_CONSTRAINTS'
        )
            ->where('CONSTRAINT_SCHEMA', $databaseName)
            ->where('TABLE_NAME', 'tanod_profiles')
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($constraintAlreadyExists) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Clean invalid legacy references safely
        |--------------------------------------------------------------------------
        |
        | Any employee_id that does not refer to an existing employee is changed
        | to null before the foreign key is created. No user, employee, incident,
        | complaint, or valid tanod profile record is deleted.
        |
        */

        DB::table('tanod_profiles')
            ->whereNotNull('employee_id')
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('employees')
                    ->whereColumn(
                        'employees.id',
                        'tanod_profiles.employee_id'
                    );
            })
            ->update([
                'employee_id' => null,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Add the foreign key after both tables exist
        |--------------------------------------------------------------------------
        */

        Schema::table('tanod_profiles', function (Blueprint $table) use (
            $constraintName
        ): void {
            $table->foreign('employee_id', $constraintName)
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('tanod_profiles')
            || ! Schema::hasColumn('tanod_profiles', 'employee_id')
        ) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $databaseName = DB::connection()->getDatabaseName();
        $constraintName = 'tanod_profiles_employee_id_foreign';

        $constraintExists = DB::table(
            'information_schema.TABLE_CONSTRAINTS'
        )
            ->where('CONSTRAINT_SCHEMA', $databaseName)
            ->where('TABLE_NAME', 'tanod_profiles')
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if (! $constraintExists) {
            return;
        }

        Schema::table('tanod_profiles', function (Blueprint $table) use (
            $constraintName
        ): void {
            $table->dropForeign($constraintName);
        });
    }
};