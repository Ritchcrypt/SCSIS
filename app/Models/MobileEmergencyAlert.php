<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileEmergencyAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_code',
        'device_id',
        'user_id',
        'installation_id',
        'request_id',
        'status',
        'latitude',
        'longitude',
        'accuracy_meters',
        'source',
        'ip_hash',
        'user_agent_hash',
        'triggered_at',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'float',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(MobileEmergencyDevice::class, 'device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->user?->name ?: 'Unidentified mobile device';
    }
}
