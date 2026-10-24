<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deleted_data_archives')) {
            return;
        }

        Schema::create('deleted_data_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_type', 80);
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_label')->nullable();
            $table->unsignedBigInteger('deleted_by_user_id')->nullable();
            $table->string('deleted_by_name')->nullable();
            $table->string('deleted_by_role', 50)->nullable();
            $table->string('source_route')->nullable();
            $table->string('source_method', 12)->nullable();
            $table->longText('payload');
            $table->timestamp('deleted_at');
            $table->unsignedBigInteger('restored_by_user_id')->nullable();
            $table->string('restored_by_name')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
            $table->index('deleted_at');
            $table->index('restored_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_data_archives');
    }
};
