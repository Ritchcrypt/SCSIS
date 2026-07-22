<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_hotlines', function (Blueprint $table) {
            $table->id();
            $table->string('agency_name');
            $table->string('hotline_number', 50);
            $table->string('color', 30)->default('blue');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        DB::table('emergency_hotlines')->insert([
            [
                'agency_name' => 'PNP (Police)',
                'hotline_number' => '117',
                'color' => 'blue',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'agency_name' => 'BFP (Fire)',
                'hotline_number' => '911',
                'color' => 'red',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'agency_name' => 'MDRRMO',
                'hotline_number' => '143',
                'color' => 'orange',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_hotlines');
    }
};