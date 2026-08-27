<?php

namespace App\Observers;

use App\Models\CaseRecord;
use App\Services\SystemNotificationService;

class CaseRecordNotificationObserver
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {
    }

    public function created(CaseRecord $caseRecord): void
    {
        $this->notifications->notifyCaseRecord($caseRecord, 'created');
    }

    public function updated(CaseRecord $caseRecord): void
    {
        if (! $caseRecord->wasChanged([
            'case_number',
            'case_type',
            'subject_name',
            'contact',
            'address',
            'incident_id',
            'incident_title',
            'status',
            'hearing_date',
            'handled_by',
            'resolution',
            'notes',
        ])) {
            return;
        }

        $this->notifications->notifyCaseRecord($caseRecord, 'updated');
    }

    public function deleted(CaseRecord $caseRecord): void
    {
        $this->notifications->notifyCaseRecord($caseRecord, 'deleted');
    }
}