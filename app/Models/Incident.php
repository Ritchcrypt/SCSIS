<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_code',
        'resident_id',
        'barangay_id',
        'category_id',
        'reporter_id',
        'assigned_to',
        'location_id',
        'status_id',
        'incident_title',
        'incident_description',
        'title',
        'description',
        'persons_involved',
        'incident_datetime',
        'reported_at',
        'priority',
        'latitude',
        'longitude',
        'map_location_name',
        'map_severity',
    ];

    protected function casts(): array
    {
        return [
            'incident_datetime' => 'datetime',
            'reported_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Incident $incident) {
            if (! $incident->incident_code) {
                $incident->forceFill([
                    'incident_code' => 'INC-' . str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignedResponder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function assignedTanod(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(GpsLocation::class, 'location_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function currentStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Evidence Relationships
    |--------------------------------------------------------------------------
    | Keep the original singular relationship, then add aliases.
    | This prevents Blade/controller mismatch between evidence/evidences/attachments.
    */

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class, 'incident_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class, 'incident_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Evidence::class, 'incident_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class, 'incident_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class, 'incident_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(IncidentEscalation::class, 'incident_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(IncidentMessage::class, 'incident_id');
    }

    public function caseRecords(): HasMany
    {
        return $this->hasMany(CaseRecord::class, 'incident_id');
    }

    public function scopePending($query)
    {
        return $query->whereHas('currentStatus', function ($statusQuery) {
            $statusQuery->whereIn('status_name', ['Pending', 'Reported', 'Acknowledged']);
        });
    }

    public function scopeAssignedTo($query, int $employeeId)
    {
        return $query->where('assigned_to', $employeeId);
    }

    public function scopeByBarangay($query, int $barangayId)
    {
        return $query->where('barangay_id', $barangayId);
    }

    public function scopeCritical($query)
    {
        return $query->where('priority', 'critical');
    }

    public function getSeverityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Low',
            'moderate' => 'Moderate',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
            default => 'Low',
        };
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->incident_title
            ?: ($this->title ?: 'Untitled Incident');
    }

    public function getDisplayCodeAttribute(): string
    {
        return $this->incident_code
            ?: 'INC-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getDisplayDescriptionAttribute(): string
    {
        return $this->incident_description
            ?: ($this->description ?: 'No description provided.');
    }

    public function getHasMapCoordinatesAttribute(): bool
{
    return $this->latitude !== null && $this->longitude !== null;
}

public function getMapSeverityValueAttribute(): string
{
    $severity = $this->map_severity;

    if (! $severity && isset($this->severity)) {
        $severity = $this->severity;
    }

    if (! $severity && isset($this->priority)) {
        $severity = $this->priority;
    }

    $severity = strtolower((string) $severity);

    return match ($severity) {
        'low' => 'low',
        'moderate', 'medium' => 'moderate',
        'high' => 'high',
        'critical', 'urgent', 'emergency' => 'critical',
        default => 'moderate',
    };
}

public function getMapSeverityLabelAttribute(): string
{
    return match ($this->map_severity_value) {
        'low' => 'Low',
        'moderate' => 'Moderate',
        'high' => 'High',
        'critical' => 'Critical',
        default => 'Moderate',
    };
}

public function getMapPinColorAttribute(): string
{
    return match ($this->map_severity_value) {
        'low' => '#22c55e',
        'moderate' => '#eab308',
        'high' => '#f97316',
        'critical' => '#ef4444',
        default => '#eab308',
    };
}

public function getMapHeatIntensityAttribute(): float
{
    return match ($this->map_severity_value) {
        'low' => 0.35,
        'moderate' => 0.55,
        'high' => 0.75,
        'critical' => 1.0,
        default => 0.55,
    };
}
}