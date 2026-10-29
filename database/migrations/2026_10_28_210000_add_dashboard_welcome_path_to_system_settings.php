<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('system_settings') && ! Schema::hasColumn('system_settings', 'dashboard_welcome_path')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->string('dashboard_welcome_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'dashboard_welcome_path')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->dropColumn('dashboard_welcome_path');
            });
        }
    }
};
