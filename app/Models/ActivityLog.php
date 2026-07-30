<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

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

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Application-level audit immutability
        |--------------------------------------------------------------------------
        |
        | Normal Eloquent operations may create audit records, but they may not
        | modify or delete an existing record. Retention pruning is performed
        | only by the controlled console command through the query builder.
        |
        */

        static::saving(function (ActivityLog $activityLog): void {
            if ($activityLog->exists) {
                throw new LogicException(
                    'Existing activity log records are immutable.'
                );
            }
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Activity log records cannot be deleted through Eloquent.'
            );
        });
    }

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
