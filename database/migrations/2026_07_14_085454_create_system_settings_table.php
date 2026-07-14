<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('system_name')->default('SCSISystem');
                $table->string('system_subtitle')->default('Dao, Capiz');
                $table->string('system_logo_path')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('system_settings') && DB::table('system_settings')->count() === 0) {
            DB::table('system_settings')->insert([
                'system_name' => 'SCSISystem',
                'system_subtitle' => 'Dao, Capiz',
                'system_logo_path' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};