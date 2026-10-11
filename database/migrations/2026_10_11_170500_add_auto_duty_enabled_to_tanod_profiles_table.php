<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tanod_profiles', 'auto_duty_enabled')) {
            return;
        }

        Schema::table('tanod_profiles', function (Blueprint $table): void {
            $table->boolean('auto_duty_enabled')
                ->default(false)
                ->after('status')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tanod_profiles', 'auto_duty_enabled')) {
            return;
        }

        Schema::table('tanod_profiles', function (Blueprint $table): void {
            $table->dropColumn('auto_duty_enabled');
        });
    }
};
