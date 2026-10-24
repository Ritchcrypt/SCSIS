<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_snapshots')) {
            return;
        }

        Schema::create('report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('range_key', 64)->unique();
            $table->string('period_key', 32)->nullable();
            $table->string('period_label', 180);
            $table->dateTime('date_from');
            $table->dateTime('date_to');
            $table->longText('payload');
            $table->string('payload_hash', 64);

            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('generated_at');
            $table->dateTime('locked_at');
            $table->timestamps();

            $table->index([
                'date_from',
                'date_to',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
