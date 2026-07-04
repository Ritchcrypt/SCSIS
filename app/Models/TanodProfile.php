<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TanodProfile extends Model
{
    protected $fillable = [
        'user_id',
        'badge_number',
        'duty_status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOnDuty(): bool
    {
        return $this->duty_status === 'on_duty';
    }

    public function isResponding(): bool
    {
        return $this->duty_status === 'responding';
    }
}