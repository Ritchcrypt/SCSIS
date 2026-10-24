<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'date_of_birth')) {
            Schema::table(
                'users',
                function (Blueprint $table): void {
                    $table
                        ->date('date_of_birth')
                        ->nullable();
                }
            );
        }

        if (! Schema::hasColumn('users', 'age_attested_at')) {
            Schema::table(
                'users',
                function (Blueprint $table): void {
                    $table
                        ->timestamp('age_attested_at')
                        ->nullable();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         *
         * This is an "ensure" migration and the columns may already have
         * existed before this migration was introduced. Dropping them on
         * rollback could destroy resident age-verification data that belongs
         * to an earlier migration or deployment.
         */
    }
};
