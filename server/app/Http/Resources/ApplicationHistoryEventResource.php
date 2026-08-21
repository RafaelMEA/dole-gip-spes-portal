<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One event on an application's merged history timeline.
 *
 * The payload is already shaped by ApplicationHistoryService; this resource
 * controls which fields leave the API. Internal identifiers beyond the event
 * id are not exposed.
 *
 * @mixin \App\Models\AuditLog
 */
class ApplicationHistoryEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'source' => $this->resource['source'],
            'action' => $this->resource['action'],
            'label' => $this->resource['label'],
            'actor' => $this->resource['actor'],
            'occurred_at' => $this->resource['occurred_at'],
            'reason' => $this->resource['reason'],
            'old_values' => $this->resource['old_values'] ?? null,
            'new_values' => $this->resource['new_values'] ?? null,
            'metadata' => $this->resource['metadata'] ?? null,
        ];
    }
}
