<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('resident_complaints')
            || ! Schema::hasColumn(
                'resident_complaints',
                'complaint_address'
            )
        ) {
            return;
        }

        Schema::table(
            'resident_complaints',
            function (Blueprint $table): void {
                /*
                 * Resident Feedback treats service area / address as optional.
                 * The original complaint schema made this column NOT NULL,
                 * while the current request validation and controller allow
                 * null. Align the database constraint with that contract.
                 */
                $table
                    ->text('complaint_address')
                    ->nullable()
                    ->change();
            }
        );
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         *
         * Once valid Resident Feedback rows contain a null address, changing
         * the column back to NOT NULL could fail or require inventing data.
         */
    }
};
