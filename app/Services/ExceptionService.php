<?php

namespace App\Services;

use App\Models\ReviewException;

class ExceptionService
{
    public function detect(string $type, string $severity, string $subjectType, ?int $subjectId, string $reasonCode, array $metrics = [], ?string $eventKey = null): ReviewException
    {
        $fingerprint = hash('sha256', implode('|', [$type, $subjectType, $subjectId ?? 'none', $reasonCode, $eventKey ?? 'default']));

        return ReviewException::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            ['type' => $type, 'severity' => $severity, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'reason_code' => $reasonCode, 'metrics' => $metrics, 'status' => 'OPEN', 'detected_at' => now()]
        );
    }

    public function review(ReviewException $exception, int $userId, ?string $note): void
    {
        if ($exception->status === 'REVIEWED') {
            return;
        }
        $exception->update(['status' => 'REVIEWED', 'reviewed_by_user_id' => $userId, 'reviewed_at' => now(), 'review_note' => $note]);
    }
}
