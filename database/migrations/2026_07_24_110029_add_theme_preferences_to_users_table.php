<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'theme_mode')) {
                $table->string('theme_mode', 20)->default('system');
            }

            if (! Schema::hasColumn('users', 'theme_custom_color')) {
                $table->string('theme_custom_color', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = [];

        if (Schema::hasColumn('users', 'theme_mode')) {
            $columns[] = 'theme_mode';
        }

        if (Schema::hasColumn('users', 'theme_custom_color')) {
            $columns[] = 'theme_custom_color';
        }

        if (! empty($columns)) {
            Schema::table('users', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};