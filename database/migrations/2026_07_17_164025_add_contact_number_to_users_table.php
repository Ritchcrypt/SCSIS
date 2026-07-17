<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'contact_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('contact_number', 30)
                    ->nullable()
                    ->after('email');
            });
        }

        /*
         * Preserve a contact value from an older column when the project
         * previously used "contact" or "phone".
         */
        if (Schema::hasColumn('users', 'contact')) {
            DB::table('users')
                ->whereNull('contact_number')
                ->whereNotNull('contact')
                ->update([
                    'contact_number' => DB::raw('contact'),
                ]);
        } elseif (Schema::hasColumn('users', 'phone')) {
            DB::table('users')
                ->whereNull('contact_number')
                ->whereNotNull('phone')
                ->update([
                    'contact_number' => DB::raw('phone'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'contact_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('contact_number');
            });
        }
    }
};
