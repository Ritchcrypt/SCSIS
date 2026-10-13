<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn(
                'incidents',
                'severity_needs_assessment'
            )
        ) {
            Schema::table(
                'incidents',
                function (Blueprint $table): void {
                    $table
                        ->boolean(
                            'severity_needs_assessment'
                        )
                        ->default(false)
                        ->after('priority');
                }
            );
        }

        if (
            ! Schema::hasColumn(
                'incidents',
                'severity_assessed_by'
            )
        ) {
            Schema::table(
                'incidents',
                function (Blueprint $table): void {
                    $table
                        ->foreignId(
                            'severity_assessed_by'
                        )
                        ->nullable()
                        ->after(
                            'severity_needs_assessment'
                        )
                        ->constrained('users')
                        ->nullOnDelete();
                }
            );
        }

        if (
            ! Schema::hasColumn(
                'incidents',
                'severity_assessed_at'
            )
        ) {
            Schema::table(
                'incidents',
                function (Blueprint $table): void {
                    $table
                        ->timestamp(
                            'severity_assessed_at'
                        )
                        ->nullable()
                        ->after(
                            'severity_assessed_by'
                        );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'incidents',
                'severity_assessed_at'
            )
        ) {
            Schema::table(
                'incidents',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'severity_assessed_at'
                    );
                }
            );
        }

        if (
            Schema::hasColumn(
                'incidents',
                'severity_assessed_by'
            )
        ) {
            Schema::table(
                'incidents',
                function (Blueprint $table): void {
                    $table->dropConstrainedForeignId(
                        'severity_assessed_by'
                    );
                }
            );
        }

        if (
            Schema::hasColumn(
                'incidents',
                'severity_needs_assessment'
            )
        ) {
            Schema::table(
                'incidents',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'severity_needs_assessment'
                    );
                }
            );
        }
    }
};
