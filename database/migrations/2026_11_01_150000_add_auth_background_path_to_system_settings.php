<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings') && ! Schema::hasColumn('system_settings', 'auth_background_path')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->string('auth_background_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Do not drop this column in production: it records the selected background.
        if (Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'auth_background_path')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->dropColumn('auth_background_path');
            });
        }
    }
};
