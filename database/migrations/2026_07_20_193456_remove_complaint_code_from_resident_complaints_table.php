<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('resident_complaints')
            && Schema::hasColumn('resident_complaints', 'complaint_code')
        ) {
            Schema::table('resident_complaints', function (Blueprint $table) {
                $table->dropUnique(['complaint_code']);
                $table->dropColumn('complaint_code');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('resident_complaints')
            && ! Schema::hasColumn('resident_complaints', 'complaint_code')
        ) {
            Schema::table('resident_complaints', function (Blueprint $table) {
                $table->string('complaint_code', 30)->nullable()->unique()->after('id');
            });
        }
    }
};