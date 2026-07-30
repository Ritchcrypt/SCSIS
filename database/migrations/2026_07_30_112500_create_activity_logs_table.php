<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the immutable security and activity audit trail.
     */
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Actor and target snapshots
            |--------------------------------------------------------------------------
            |
            | These are intentionally not foreign keys. Security history must
            | remain readable even after an account is deleted or anonymised.
            |
            */

            $table->unsignedBigInteger('actor_id')
                ->nullable()
                ->index();

            $table->string('actor_name')
                ->nullable();

            $table->string('actor_role', 50)
                ->nullable();

            $table->unsignedBigInteger('target_user_id')
                ->nullable()
                ->index();

            $table->string('target_name')
                ->nullable();

            $table->string('target_role', 50)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Event details
            |--------------------------------------------------------------------------
            */

            $table->string('event', 100)
                ->index();

            $table->string('category', 50)
                ->index();

            $table->string('description', 500);

            $table->string('route_name', 150)
                ->nullable();

            $table->string('request_method', 10)
                ->nullable();

            $table->string('ip_address', 45)
                ->nullable()
                ->index();

            $table->string('user_agent', 500)
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamp('created_at')
                ->useCurrent()
                ->index();

            $table->index([
                'category',
                'event',
            ]);
        });
    }

    /**
     * Reverse only the table introduced by this migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
