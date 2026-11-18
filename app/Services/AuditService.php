<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditService
{
    public function record(string $action, string $entityType, ?int $entityId, array $old = [], array $new = [], ?string $summary = null, ?int $userId = null): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $userId ?? auth()->id(), 'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId,
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(), 'summary' => $summary, 'created_at' => now(),
        ]);
    }
}
