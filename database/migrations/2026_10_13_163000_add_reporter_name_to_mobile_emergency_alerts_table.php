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
                'mobile_emergency_alerts',
                'reporter_name'
            )
        ) {
            Schema::table(
                'mobile_emergency_alerts',
                function (Blueprint $table): void {
                    $table
                        ->string(
                            'reporter_name',
                            255
                        )
                        ->nullable()
                        ->after(
                            'user_id'
                        );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'mobile_emergency_alerts',
                'reporter_name'
            )
        ) {
            Schema::table(
                'mobile_emergency_alerts',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'reporter_name'
                    );
                }
            );
        }
    }
};
