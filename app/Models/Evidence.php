<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

class Evidence extends Model
{
    use HasFactory;

    protected $table = 'evidence';

    protected $fillable = [
        'incident_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (
            ! $this->exists
            || ! Route::has(
                'incident-evidence.file'
            )
        ) {
            return null;
        }

        return route(
            'incident-evidence.file',
            [
                'evidenceId' => $this->getKey(),
            ]
        );
    }
}
