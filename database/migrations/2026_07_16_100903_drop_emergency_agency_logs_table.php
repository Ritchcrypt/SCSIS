<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('emergency_agency_logs');
    }

    public function down(): void
    {
        // The removed emergency notification logging system will not be restored.
    }
};