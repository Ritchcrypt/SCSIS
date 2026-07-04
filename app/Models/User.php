<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function initials(): string
{
    $name = trim((string) $this->name);

    if ($name === '') {
        $name = trim((string) $this->email);
    }

    $words = preg_split('/\s+/', $name);

    if (! $words || count($words) === 0) {
        return 'U';
    }

    $initials = '';

    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= strtoupper(mb_substr($word, 0, 1));
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials ?: 'U';
}

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function resident(): HasOne
    {
        return $this->hasOne(Resident::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function reportedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'reporter_id');
    }

    public function createdAnnouncements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function systemNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'user_id');
    }

    public function uploadedEvidence(): HasMany
    {
        return $this->hasMany(Evidence::class, 'uploaded_by');
    }

    public function statusUpdates(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class, 'updated_by');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isResident(): bool
    {
        return $this->hasRole('resident');
    }

    public function isTanod(): bool
    {
        return $this->hasRole('tanod');
    }

    public function isOfficial(): bool
    {
        return $this->hasRole(['official', 'dao']);
    }

    public function isActive(): bool
    {
        return (bool) $this->status;
    }
}