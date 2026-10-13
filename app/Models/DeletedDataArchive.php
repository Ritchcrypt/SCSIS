<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedDataArchive extends Model
{
    protected $fillable = [
        'resource_type',
        'resource_id',
        'resource_label',
        'deleted_by_user_id',
        'deleted_by_name',
        'deleted_by_role',
        'source_route',
        'source_method',
        'payload',
        'deleted_at',
        'restored_by_user_id',
        'restored_by_name',
        'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'resource_id' => 'integer',
            'payload' => 'encrypted:array',
            'deleted_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function getIsRestoredAttribute(): bool
    {
        return $this->restored_at !== null;
    }
}
