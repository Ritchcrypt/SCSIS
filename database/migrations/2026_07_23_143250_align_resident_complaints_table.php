<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resident_complaints')) {
            Schema::create('resident_complaints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('resident_id')->nullable()->index();
                $table->string('complainant_name');
                $table->string('contact_number')->nullable();
                $table->text('complaint_description');
                $table->string('complaint_address')->nullable();
                $table->string('evidence_path')->nullable();
                $table->string('status')->default('submitted');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('resident_complaints', function (Blueprint $table) {
            if (! Schema::hasColumn('resident_complaints', 'resident_id')) {
                $table->unsignedBigInteger('resident_id')->nullable()->index()->after('id');
            }

            if (! Schema::hasColumn('resident_complaints', 'complainant_name')) {
                $table->string('complainant_name')->after('resident_id');
            }

            if (! Schema::hasColumn('resident_complaints', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('complainant_name');
            }

            if (! Schema::hasColumn('resident_complaints', 'complaint_description')) {
                $table->text('complaint_description')->after('contact_number');
            }

            if (! Schema::hasColumn('resident_complaints', 'complaint_address')) {
                $table->string('complaint_address')->nullable()->after('complaint_description');
            }

            if (! Schema::hasColumn('resident_complaints', 'evidence_path')) {
                $table->string('evidence_path')->nullable()->after('complaint_address');
            }

            if (! Schema::hasColumn('resident_complaints', 'status')) {
                $table->string('status')->default('submitted')->after('evidence_path');
            }

            if (! Schema::hasColumn('resident_complaints', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('resident_complaints', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('resident_complaints', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        if (
            Schema::hasColumn('resident_complaints', 'resident_id')
            && Schema::hasColumn('resident_complaints', 'user_id')
        ) {
            DB::table('resident_complaints')
                ->whereNull('resident_id')
                ->update([
                    'resident_id' => DB::raw('user_id'),
                ]);
        }
    }

    public function down(): void
    {
        // Safe rollback: do not drop columns automatically because this table may already contain real complaint data.
    }
};