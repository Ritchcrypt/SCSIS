<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TanodTask extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'description',
        'location',
        'task_datetime',
        'due_at',
        'priority',
        'status',
    ];

    protected $casts = [
        'task_datetime' => 'datetime',
        'due_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TanodTaskResponse::class);
    }

    public function acceptedResponses(): HasMany
    {
        return $this->hasMany(TanodTaskResponse::class)
            ->where('response_status', 'accepted');
    }

    public function declinedResponses(): HasMany
    {
        return $this->hasMany(TanodTaskResponse::class)
            ->where('response_status', 'declined');
    }

    public function pendingResponses(): HasMany
    {
        return $this->hasMany(TanodTaskResponse::class)
            ->where('response_status', 'pending');
    }
}