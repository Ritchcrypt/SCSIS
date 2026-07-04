<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay_name',
        'location',
    ];

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}