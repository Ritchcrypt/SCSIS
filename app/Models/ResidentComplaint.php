<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentComplaint extends Model
{
    protected $fillable = [
        'resident_id',
        'complainant_name',
        'contact_number',
        'feedback_category',
        'feedback_type',
        'feedback_subject',
        'service_rating',
        'complaint_description',
        'complaint_address',
        'evidence_path',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'service_rating' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ResidentComplaint $complaint): void {
            if (empty($complaint->submitted_at)) {
                $complaint->submitted_at = now();
            }
        });
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function feedbackCategoryLabel(): string
    {
        return match ((string) $this->feedback_category) {
            'system_experience' => 'TabangNow System Experience',
            'staff_response' => 'Staff Response & Action',
            'barangay_service' => 'Barangay Service',
            'community_concern' => 'Community Concern',
            'suggestion' => 'Suggestion / Improvement',
            default => 'Community Concern',
        };
    }

    public function feedbackTypeLabel(): string
    {
        return match ((string) $this->feedback_type) {
            'complaint' => 'Complaint',
            'suggestion' => 'Suggestion',
            'compliment' => 'Compliment',
            'concern' => 'Concern',
            default => 'Concern',
        };
    }
}
