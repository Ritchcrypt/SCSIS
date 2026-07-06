<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'acknowledged_by')) {
                $table->unsignedBigInteger('acknowledged_by')
                    ->nullable()
                    ->after('read_at')
                    ->index();
            }

            if (! Schema::hasColumn('notifications', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')
                    ->nullable()
                    ->after('acknowledged_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'acknowledged_at')) {
                $table->dropColumn('acknowledged_at');
            }

            if (Schema::hasColumn('notifications', 'acknowledged_by')) {
                $table->dropIndex(['acknowledged_by']);
                $table->dropColumn('acknowledged_by');
            }
        });
    }
};