<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobilePushToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'installation_id',
        'fcm_token',
        'token_hash',
        'device_name',
        'platform',
        'last_seen_at',
        'revoked_at',
    ];

    protected $hidden = [
        'fcm_token',
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'fcm_token' => 'encrypted',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
