<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TanodTaskResponse extends Model
{
    protected $fillable = [
        'tanod_task_id',
        'employee_id',
        'user_id',
        'response_status',
        'response_note',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TanodTask::class, 'tanod_task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}