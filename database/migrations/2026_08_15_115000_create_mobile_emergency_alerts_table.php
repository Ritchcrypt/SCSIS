<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_code', 40)->unique();
            $table->foreignId('device_id')->nullable()->constrained('mobile_emergency_devices')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('installation_id', 120)->index();
            $table->string('request_id', 120)->unique();
            $table->string('status', 30)->default('active')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->string('source', 30)->default('mobile');
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('triggered_at')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'triggered_at']);
            $table->index(['device_id', 'triggered_at']);
            $table->index(['installation_id', 'status', 'triggered_at'], 'mobile_sos_installation_status_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_emergency_alerts');
    }
};
