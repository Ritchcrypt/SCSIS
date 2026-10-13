<?php

use App\Support\DaoBarangays;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureOfficialDaoBarangays();
        $this->ensureUserBarangayColumn();
        $this->ensureTanodTaskBarangayColumns();
        $this->backfillIncidentTaskBarangays();
        $this->backfillTanodUserBarangays();
    }

    private function ensureOfficialDaoBarangays(): void
    {
        if (! Schema::hasTable('barangays')) {
            return;
        }

        $now = now();

        $legacyIlasNorteId = DB::table('barangays')
            ->where('barangay_name', 'Ilas Norte')
            ->value('id');

        $sanAgustinExists = DB::table('barangays')
            ->where('barangay_name', 'San Agustin')
            ->exists();

        if ($legacyIlasNorteId && ! $sanAgustinExists) {
            DB::table('barangays')
                ->where('id', $legacyIlasNorteId)
                ->update([
                    'barangay_name' => 'San Agustin',
                    'location' => 'Dao, Capiz',
                    'updated_at' => $now,
                ]);
        }

        foreach (DaoBarangays::records() as $barangay) {
            $existingId = DB::table('barangays')
                ->where(
                    'barangay_name',
                    $barangay['name']
                )
                ->value('id');

            if ($existingId) {
                DB::table('barangays')
                    ->where('id', $existingId)
                    ->update([
                        'location' => 'Dao, Capiz',
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('barangays')->insert([
                'barangay_name' => $barangay['name'],
                'location' => 'Dao, Capiz',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function ensureUserBarangayColumn(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('barangays')
            || Schema::hasColumn(
                'users',
                'barangay_id'
            )
        ) {
            return;
        }

        Schema::table(
            'users',
            function (Blueprint $table): void {
                $table->foreignId('barangay_id')
                    ->nullable()
                    ->constrained('barangays')
                    ->nullOnDelete();
            }
        );
    }

    private function ensureTanodTaskBarangayColumns(): void
    {
        if (
            ! Schema::hasTable('tanod_tasks')
            || ! Schema::hasTable('barangays')
        ) {
            return;
        }

        $hasBarangayId = Schema::hasColumn(
            'tanod_tasks',
            'barangay_id'
        );

        $hasFallbackUsed = Schema::hasColumn(
            'tanod_tasks',
            'fallback_used'
        );

        Schema::table(
            'tanod_tasks',
            function (Blueprint $table) use (
                $hasBarangayId,
                $hasFallbackUsed
            ): void {
                if (! $hasBarangayId) {
                    $table->foreignId(
                        'barangay_id'
                    )
                        ->nullable()
                        ->after('incident_id')
                        ->constrained('barangays')
                        ->nullOnDelete();
                }

                if (! $hasFallbackUsed) {
                    $table->boolean(
                        'fallback_used'
                    )
                        ->default(false)
                        ->after('barangay_id');
                }
            }
        );
    }

    private function backfillIncidentTaskBarangays(): void
    {
        if (
            ! Schema::hasTable('tanod_tasks')
            || ! Schema::hasTable('incidents')
            || ! Schema::hasColumn(
                'tanod_tasks',
                'incident_id'
            )
            || ! Schema::hasColumn(
                'tanod_tasks',
                'barangay_id'
            )
            || ! Schema::hasColumn(
                'incidents',
                'barangay_id'
            )
        ) {
            return;
        }

        DB::table('tanod_tasks')
            ->whereNull('barangay_id')
            ->whereNotNull('incident_id')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($tasks): void {
                    foreach ($tasks as $task) {
                        $barangayId =
                            DB::table('incidents')
                                ->where(
                                    'id',
                                    $task->incident_id
                                )
                                ->value('barangay_id');

                        if (! $barangayId) {
                            continue;
                        }

                        DB::table('tanod_tasks')
                            ->where('id', $task->id)
                            ->update([
                                'barangay_id' =>
                                    (int) $barangayId,
                            ]);
                    }
                }
            );
    }

    private function backfillTanodUserBarangays(): void
    {
        if (
            ! Schema::hasTable('employees')
            || ! Schema::hasTable('users')
            || ! Schema::hasColumn(
                'employees',
                'barangay_id'
            )
            || ! Schema::hasColumn(
                'users',
                'barangay_id'
            )
        ) {
            return;
        }

        DB::table('employees')
            ->whereNotNull('user_id')
            ->whereNotNull('barangay_id')
            ->where('employee_type', 'tanod')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($employees): void {
                    foreach ($employees as $employee) {
                        DB::table('users')
                            ->where(
                                'id',
                                $employee->user_id
                            )
                            ->whereNull('barangay_id')
                            ->update([
                                'barangay_id' =>
                                    (int) $employee
                                        ->barangay_id,
                            ]);
                    }
                }
            );
    }

    public function down(): void
    {
        // Intentionally non-destructive.
        // Barangay routing fields may contain real operational assignments.
    }
};
