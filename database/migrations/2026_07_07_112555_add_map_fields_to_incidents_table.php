<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incidents')) {
            return;
        }

        Schema::table('incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('incidents', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('incidents', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('incidents', 'map_location_name')) {
                $table->string('map_location_name', 255)->nullable();
            }

            if (! Schema::hasColumn('incidents', 'map_severity')) {
                $table->string('map_severity', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('incidents')) {
            return;
        }

        Schema::table('incidents', function (Blueprint $table) {
            if (Schema::hasColumn('incidents', 'map_severity')) {
                $table->dropColumn('map_severity');
            }

            if (Schema::hasColumn('incidents', 'map_location_name')) {
                $table->dropColumn('map_location_name');
            }

            if (Schema::hasColumn('incidents', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::hasColumn('incidents', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};