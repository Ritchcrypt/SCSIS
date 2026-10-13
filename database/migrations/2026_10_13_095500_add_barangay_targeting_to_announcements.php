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
        if (! Schema::hasTable('barangays')) {
            return;
        }

        $now = now();

        /*
         * PSA historically published San Agustin as "San Agustin (Ilas Norte)".
         * The existing project seed used "Ilas Norte". Rename that exact legacy
         * row only when San Agustin does not already exist. Keeping the same row
         * ID preserves users/incidents/employees that already reference it.
         */
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
                ->where('barangay_name', $barangay['name'])
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

        if (
            Schema::hasTable('users')
            && ! Schema::hasColumn('users', 'barangay_id')
        ) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('barangay_id')
                    ->nullable()
                    ->constrained('barangays')
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasTable('announcements')
            && ! Schema::hasTable('announcement_barangay')
        ) {
            Schema::create(
                'announcement_barangay',
                function (Blueprint $table): void {
                    $table->id();

                    $table->foreignId('announcement_id')
                        ->constrained('announcements')
                        ->cascadeOnDelete();

                    $table->foreignId('barangay_id')
                        ->constrained('barangays')
                        ->cascadeOnDelete();

                    $table->timestamps();

                    $table->unique([
                        'announcement_id',
                        'barangay_id',
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_barangay');

        // Intentionally preserve the official Dao barangay reference rows and
        // users.barangay_id because they may already contain real records.
    }
};
