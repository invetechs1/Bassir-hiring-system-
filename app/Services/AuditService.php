<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public function log(?int $actorId, string $action, string $entity, ?string $entityId = null, array $metadata = [], ?Request $request = null): void
    {
        $enriched = array_filter([
            ...$metadata,
            'trace_id' => $request?->attributes->get('trace_id'),
            'user_agent' => $request?->userAgent(),
            'request_path' => $request?->path(),
        ], fn ($value) => ! is_null($value));

        AuditLog::create([
            'company_id' => $request?->user()?->company_id,
            'actor_id' => $actorId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'metadata' => $enriched ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }
}
