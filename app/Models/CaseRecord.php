<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_number',
        'case_type',
        'subject_name',
        'contact',
        'address',
        'incident_id',
        'incident_title',
        'status',
        'hearing_date',
        'handled_by',
        'resolution',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hearing_date' => 'date',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Open',
            'under_investigation' => 'Under Investigation',
            'mediation' => 'Mediation',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getDisplayTypeAttribute(): string
    {
        return match ($this->case_type) {
            'blotter' => 'Blotter',
            'mediation' => 'Mediation',
            'complaint' => 'Complaint',
            'referral' => 'Referral',
            default => ucfirst(str_replace('_', ' ', (string) $this->case_type)),
        };
    }

    public function getDisplayIncidentTitleAttribute(): string
    {
        return $this->incident?->display_title
            ?: ($this->incident_title ?: 'No linked incident');
    }
}