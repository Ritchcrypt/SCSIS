<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Incidents table alignment
        |--------------------------------------------------------------------------
        | Keeps existing incidents table, but adds fields needed by the prototype.
        */

        if (Schema::hasTable('incidents')) {
            Schema::table('incidents', function (Blueprint $table) {
                if (! Schema::hasColumn('incidents', 'incident_code')) {
                    $table->string('incident_code', 30)
                        ->nullable()
                        ->unique()
                        ->after('id');
                }

                if (! Schema::hasColumn('incidents', 'persons_involved')) {
                    $table->text('persons_involved')
                        ->nullable()
                        ->after('incident_description');
                }
            });

            if (Schema::hasColumn('incidents', 'priority')) {
                /*
                 * MySQL supports ALTER TABLE ... MODIFY ENUM.
                 * SQLite does not, so CI tests skip only the ENUM structure changes.
                 * The data update still runs safely.
                 */
                if (DB::getDriverName() !== 'sqlite') {
                    DB::statement("ALTER TABLE incidents MODIFY priority ENUM('low', 'normal', 'moderate', 'high', 'critical') NOT NULL DEFAULT 'low'");
                }

                DB::table('incidents')
                    ->where('priority', 'normal')
                    ->update(['priority' => 'moderate']);

                if (DB::getDriverName() !== 'sqlite') {
                    DB::statement("ALTER TABLE incidents MODIFY priority ENUM('low', 'moderate', 'high', 'critical') NOT NULL DEFAULT 'low'");
                }
            } else {
                Schema::table('incidents', function (Blueprint $table) {
                    $table->enum('priority', ['low', 'moderate', 'high', 'critical'])
                        ->default('low')
                        ->after('incident_datetime');
                });
            }

            $incidents = DB::table('incidents')
                ->whereNull('incident_code')
                ->select('id')
                ->get();

            foreach ($incidents as $incident) {
                DB::table('incidents')
                    ->where('id', $incident->id)
                    ->update([
                        'incident_code' => 'INC-' . str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT),
                    ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | GPS locations
        |--------------------------------------------------------------------------
        | Prototype shows Purok in incident details.
        */

        if (Schema::hasTable('gps_locations') && ! Schema::hasColumn('gps_locations', 'purok')) {
            Schema::table('gps_locations', function (Blueprint $table) {
                $table->string('purok', 100)
                    ->nullable()
                    ->after('location_address');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Incident escalations
        |--------------------------------------------------------------------------
        | Supports "Escalate to Agency" panel.
        */

        if (! Schema::hasTable('incident_escalations')) {
            Schema::create('incident_escalations', function (Blueprint $table) {
                $table->id();

                $table->foreignId('incident_id')
                    ->constrained('incidents')
                    ->cascadeOnDelete();

                $table->foreignId('escalated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('agency', 100);
                $table->text('reason')->nullable();
                $table->timestamp('escalated_at')->useCurrent();

                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Incident messages
        |--------------------------------------------------------------------------
        | Supports communication box in incident view page.
        */

        if (! Schema::hasTable('incident_messages')) {
            Schema::create('incident_messages', function (Blueprint $table) {
                $table->id();

                $table->foreignId('incident_id')
                    ->constrained('incidents')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('message');

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_messages');
        Schema::dropIfExists('incident_escalations');

        if (Schema::hasTable('gps_locations') && Schema::hasColumn('gps_locations', 'purok')) {
            Schema::table('gps_locations', function (Blueprint $table) {
                $table->dropColumn('purok');
            });
        }

        if (Schema::hasTable('incidents')) {
            if (Schema::hasColumn('incidents', 'persons_involved')) {
                Schema::table('incidents', function (Blueprint $table) {
                    $table->dropColumn('persons_involved');
                });
            }

            if (Schema::hasColumn('incidents', 'incident_code')) {
                Schema::table('incidents', function (Blueprint $table) {
                    try {
                        $table->dropUnique('incidents_incident_code_unique');
                    } catch (Throwable $e) {
                        //
                    }

                    $table->dropColumn('incident_code');
                });
            }

            if (Schema::hasColumn('incidents', 'priority') && DB::getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE incidents MODIFY priority ENUM('low', 'normal', 'high', 'critical') NOT NULL DEFAULT 'normal'");
            }
        }
    }
};