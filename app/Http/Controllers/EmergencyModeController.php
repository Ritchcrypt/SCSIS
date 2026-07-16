<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class EmergencyModeController extends Controller
{
    public function index(): View
    {
        return view('emergency-mode.index', [
            'agencies' => $this->agencies(),
        ]);
    }

    private function agencies(): array
    {
        return [
            'pnp' => [
                'name' => 'PNP (Police)',
                'short_name' => 'PNP',
                'hotline' => '117',
                'color' => 'blue',
            ],
            'bfp' => [
                'name' => 'BFP (Fire)',
                'short_name' => 'BFP',
                'hotline' => '911',
                'color' => 'red',
            ],
            'mdrrmo' => [
                'name' => 'MDRRMO',
                'short_name' => 'MDRRMO',
                'hotline' => '143',
                'color' => 'orange',
            ],
        ];
    }
}