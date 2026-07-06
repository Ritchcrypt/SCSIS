<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'category',
        'priority',
        'audience',
        'is_active',
        'activate_calamity_mode',
        'posted_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activate_calamity_mode' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function getDisplayCategoryAttribute(): string
    {
        return match ($this->category) {
            'advisory' => 'Advisory',
            'emergency' => 'Emergency',
            'calamity' => 'Calamity',
            'community' => 'Community',
            'health' => 'Health',
            'general' => 'General',
            default => ucfirst(str_replace('_', ' ', (string) $this->category)),
        };
    }

    public function getDisplayPriorityAttribute(): string
    {
        return match ($this->priority) {
            'normal' => 'Normal',
            'important' => 'Important',
            'urgent' => 'Urgent',
            'emergency' => 'Emergency',
            default => ucfirst(str_replace('_', ' ', (string) $this->priority)),
        };
    }

    public function getDisplayAudienceAttribute(): string
    {
        return match ($this->audience) {
            'everyone' => 'All',
            'tanod' => 'Tanod',
            'residents' => 'Residents',
            'admin' => 'Admin',
            default => ucfirst(str_replace('_', ' ', (string) $this->audience)),
        };
    }
}