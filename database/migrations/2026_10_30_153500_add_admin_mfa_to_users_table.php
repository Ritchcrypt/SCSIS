<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('mfa_secret')->nullable();
            $table->unsignedBigInteger('mfa_last_used_step')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
        });
    }

    public function down(): void
    {
        // Use rollback only in disposable/test databases. Deleting MFA fields
        // from a production database would remove security enrolment state.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'mfa_secret', 'mfa_last_used_step',
                'mfa_recovery_codes', 'mfa_enabled_at',
            ]);
        });
    }
};
