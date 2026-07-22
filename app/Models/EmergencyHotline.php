<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyHotline extends Model
{
    protected $fillable = [
        'agency_name',
        'hotline_number',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}