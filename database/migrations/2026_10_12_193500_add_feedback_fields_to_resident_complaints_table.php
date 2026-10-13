<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resident_complaints')) {
            return;
        }

        $hasCategory = Schema::hasColumn('resident_complaints', 'feedback_category');
        $hasType = Schema::hasColumn('resident_complaints', 'feedback_type');
        $hasSubject = Schema::hasColumn('resident_complaints', 'feedback_subject');
        $hasRating = Schema::hasColumn('resident_complaints', 'service_rating');

        Schema::table('resident_complaints', function (Blueprint $table) use (
            $hasCategory,
            $hasType,
            $hasSubject,
            $hasRating
        ): void {
            if (! $hasCategory) {
                $table->string('feedback_category', 50)
                    ->default('community_concern')
                    ->index()
                    ->after('contact_number');
            }

            if (! $hasType) {
                $table->string('feedback_type', 30)
                    ->default('concern')
                    ->index()
                    ->after('feedback_category');
            }

            if (! $hasSubject) {
                $table->string('feedback_subject')
                    ->nullable()
                    ->after('feedback_type');
            }

            if (! $hasRating) {
                $table->unsignedTinyInteger('service_rating')
                    ->nullable()
                    ->after('feedback_subject');
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
        // Feedback columns may contain real resident data, so rollback does not drop them.
    }
};
