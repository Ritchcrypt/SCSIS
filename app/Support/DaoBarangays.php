<?php

namespace App\Support;

final class DaoBarangays
{
    public const MUNICIPALITY_PSGC_CODE = '0601902000';

    /**
     * Official Dao, Capiz barangays based on the Philippine Standard
     * Geographic Code (PSGC).
     *
     * @return array<int, array{name:string, psgc_code:string}>
     */
    public static function records(): array
    {
        return [
            ['name' => 'Aganan', 'psgc_code' => '0601902001'],
            ['name' => 'Agtambi', 'psgc_code' => '0601902002'],
            ['name' => 'Agtanguay', 'psgc_code' => '0601902003'],
            ['name' => 'Balucuan', 'psgc_code' => '0601902004'],
            ['name' => 'Bita', 'psgc_code' => '0601902005'],
            ['name' => 'Centro', 'psgc_code' => '0601902006'],
            ['name' => 'Daplas', 'psgc_code' => '0601902007'],
            ['name' => 'Duyoc', 'psgc_code' => '0601902008'],
            ['name' => 'Ilas Sur', 'psgc_code' => '0601902009'],
            ['name' => 'Lacaron', 'psgc_code' => '0601902012'],
            ['name' => 'Malonoy', 'psgc_code' => '0601902013'],
            ['name' => 'Manhoy', 'psgc_code' => '0601902014'],
            ['name' => 'Mapulang Bato', 'psgc_code' => '0601902015'],
            ['name' => 'Matagnop', 'psgc_code' => '0601902016'],
            ['name' => 'Nasunogan', 'psgc_code' => '0601902017'],
            ['name' => 'Poblacion Ilawod', 'psgc_code' => '0601902018'],
            ['name' => 'Poblacion Ilaya', 'psgc_code' => '0601902019'],
            ['name' => 'Quinabcaban', 'psgc_code' => '0601902020'],
            ['name' => 'Quinayuya', 'psgc_code' => '0601902021'],
            ['name' => 'San Agustin', 'psgc_code' => '0601902022'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::records(), 'name');
    }
}
