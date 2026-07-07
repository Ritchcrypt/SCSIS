<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyAgencyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency',
        'agency_name',
        'hotline',
        'message',
        'incident_id',
        'status',
        'initiated_by',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'contacted' => 'Contacted',
            'responding' => 'Responding',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'contacted' => 'bg-blue-100 text-blue-700 border-blue-200',
            'responding' => 'bg-orange-100 text-orange-700 border-orange-200',
            'completed' => 'bg-green-100 text-green-700 border-green-200',
            'cancelled' => 'bg-red-100 text-red-700 border-red-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }
}