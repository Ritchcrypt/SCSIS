<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('barangays')) {
            return;
        }

        $barangays = [
            'Aganan',
            'Agtambi',
            'Agtanguay',
            'Balucuan',
            'Bita',
            'Centro',
            'Daplas',
            'Duyoc',
            'Ilas Sur',
            'Lacaron',
            'Malonoy',
            'Manhoy',
            'Mapulang Bato',
            'Matagnop',
            'Nasunogan',
            'Poblacion Ilawod',
            'Poblacion Ilaya',
            'Quinabcaban',
            'Quinayuya',
            'San Agustin',
        ];

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

        foreach ($barangays as $barangayName) {
            $exists = DB::table('barangays')
                ->where('barangay_name', $barangayName)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('barangays')->insert([
                'barangay_name' => $barangayName,
                'location' => 'Dao, Capiz',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive.
        // Barangays may already be referenced by operational records.
    }
};
