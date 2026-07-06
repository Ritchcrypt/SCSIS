<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'source_id',
        'title',
        'message',
        'is_read',
        'read_at',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeType($query, ?string $type)
    {
        if (! $type || $type === 'all') {
            return $query;
        }

        return $query->where('type', $type);
    }

    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function acknowledge(int $userId): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => $this->read_at ?: now(),
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
        ]);
    }

    public function getIsAcknowledgedAttribute(): bool
    {
        return ! is_null($this->acknowledged_at) || (bool) $this->is_read;
    }
}