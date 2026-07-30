<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_role',
        'target_user_id',
        'target_name',
        'target_role',
        'event',
        'category',
        'description',
        'route_name',
        'request_method',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'target_user_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
