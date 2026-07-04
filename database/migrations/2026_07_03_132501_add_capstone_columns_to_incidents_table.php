<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incidents')) {
            Schema::create('incidents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
                $table->foreignId('barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('incident_categories')->nullOnDelete();
                $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('gps_locations')->nullOnDelete();
                $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();

                $table->string('incident_title', 150)->nullable();
                $table->text('incident_description')->nullable();
                $table->dateTime('incident_datetime')->nullable();
                $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');

                $table->timestamps();
            });

            return;
        }

        Schema::table('incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('incidents', 'resident_id')) {
                $table->foreignId('resident_id')->nullable()->after('id')->constrained('residents')->nullOnDelete();
            }

            if (! Schema::hasColumn('incidents', 'barangay_id')) {
                $table->foreignId('barangay_id')->nullable()->after('resident_id')->constrained('barangays')->nullOnDelete();
            }

            if (! Schema::hasColumn('incidents', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('barangay_id')->constrained('incident_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('incidents', 'reporter_id')) {
                $table->foreignId('reporter_id')->nullable()->after('category_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('incidents', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('reporter_id')->constrained('employees')->nullOnDelete();
            }

            if (! Schema::hasColumn('incidents', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('assigned_to')->constrained('gps_locations')->nullOnDelete();
            }

            if (! Schema::hasColumn('incidents', 'status_id')) {
                $table->foreignId('status_id')->nullable()->after('location_id')->constrained('statuses')->nullOnDelete();
            }

            if (! Schema::hasColumn('incidents', 'incident_title')) {
                $table->string('incident_title', 150)->nullable()->after('status_id');
            }

            if (! Schema::hasColumn('incidents', 'incident_description')) {
                $table->text('incident_description')->nullable()->after('incident_title');
            }

            if (! Schema::hasColumn('incidents', 'incident_datetime')) {
                $table->dateTime('incident_datetime')->nullable()->after('incident_description');
            }

            if (! Schema::hasColumn('incidents', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'high', 'critical'])
                    ->default('normal')
                    ->after('incident_datetime');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('incidents')) {
            return;
        }

        Schema::table('incidents', function (Blueprint $table) {
            $columns = [
                'resident_id',
                'barangay_id',
                'category_id',
                'reporter_id',
                'assigned_to',
                'location_id',
                'status_id',
                'incident_title',
                'incident_description',
                'incident_datetime',
                'priority',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('incidents', $column)) {
                    try {
                        $table->dropForeign([$column]);
                    } catch (Throwable $e) {
                        // Column may not have a foreign key. Continue safely.
                    }
                }
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn('incidents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};