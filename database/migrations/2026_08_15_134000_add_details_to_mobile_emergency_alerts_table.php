<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_emergency_alerts', function (Blueprint $table) {
            $table->text('emergency_details')->nullable()->after('status');
            $table->string('contact_number', 20)->nullable()->after('emergency_details');
            $table->string('location_source', 20)->nullable()->after('accuracy_meters');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_emergency_alerts', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_details',
                'contact_number',
                'location_source',
            ]);
        });
    }
};
