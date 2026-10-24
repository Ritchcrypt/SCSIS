<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSnapshot extends Model
{
    protected $fillable = [
        'range_key',
        'period_key',
        'period_label',
        'date_from',
        'date_to',
        'payload',
        'payload_hash',
        'generated_by',
        'generated_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'datetime',
            'date_to' => 'datetime',
            'payload' => 'encrypted:array',
            'generated_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }
}
