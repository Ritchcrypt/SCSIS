<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TanodProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_id',
        'badge_number',
        'contact_number',
        'purok_assignment',
        'date_appointed',
        'shift',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_appointed' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getDisplayShiftAttribute(): string
    {
        return match ($this->shift) {
            'day' => 'Day',
            'afternoon' => 'Afternoon',
            'night' => 'Night',
            'floating' => 'Floating',
            default => ucfirst(str_replace('_', ' ', (string) $this->shift)),
        };
    }

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'on_duty' => 'On Duty',
            'off_duty' => 'Off Duty',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'active' => 'bg-green-100 text-green-700 border-green-200',
            'on_duty' => 'bg-blue-100 text-blue-700 border-blue-200',
            'off_duty' => 'bg-slate-100 text-slate-700 border-slate-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }
}