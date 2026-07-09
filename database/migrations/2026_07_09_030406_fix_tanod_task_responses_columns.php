<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tanod_task_responses')) {
            Schema::create('tanod_task_responses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tanod_task_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('response_status')->default('pending');
                $table->text('response_note')->nullable();
                $table->dateTime('responded_at')->nullable();
                $table->timestamps();

                $table->unique(['tanod_task_id', 'employee_id']);
            });

            return;
        }

        Schema::table('tanod_task_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('tanod_task_responses', 'tanod_task_id')) {
                $table->unsignedBigInteger('tanod_task_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('tanod_task_responses', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('tanod_task_id');
            }

            if (! Schema::hasColumn('tanod_task_responses', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('employee_id');
            }

            if (! Schema::hasColumn('tanod_task_responses', 'response_status')) {
                $table->string('response_status')->default('pending')->after('user_id');
            }

            if (! Schema::hasColumn('tanod_task_responses', 'response_note')) {
                $table->text('response_note')->nullable()->after('response_status');
            }

            if (! Schema::hasColumn('tanod_task_responses', 'responded_at')) {
                $table->dateTime('responded_at')->nullable()->after('response_note');
            }

            if (! Schema::hasColumn('tanod_task_responses', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('tanod_task_responses', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Do not drop columns automatically to avoid deleting task response data.
    }
};