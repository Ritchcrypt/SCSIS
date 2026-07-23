<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'show_in_weather_feed')) {
                $table->boolean('show_in_weather_feed')
                    ->default(false)
                    ->after('activate_calamity_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'show_in_weather_feed')) {
                $table->dropColumn('show_in_weather_feed');
            }
        });
    }
};