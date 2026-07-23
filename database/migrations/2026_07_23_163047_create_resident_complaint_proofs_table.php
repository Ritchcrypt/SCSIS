<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resident_complaint_proofs')) {
            return;
        }

        Schema::create('resident_complaint_proofs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resident_complaint_id')->index();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->string('proof_path');
            $table->text('proof_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_complaint_proofs');
    }
};