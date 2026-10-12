<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;

class AuditService
{
    public function log(string $actionType, string $subjectType, ?int $subjectId = null, ?User $actor = null, ?array $metadata = null): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'action_type' => $actionType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
        ]);
    }
}
