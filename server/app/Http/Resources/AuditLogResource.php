<?php

namespace App\Http\Resources;

use App\Services\AuditActionLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A read-only audit entry for a contextual entity history (deployment slot,
 * assignment). The acting user is exposed by name only; internal user ids,
 * storage paths and other sensitive details never leave the API.
 *
 * @property \App\Models\AuditLog $resource
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'label' => AuditActionLabels::audit($this->action),
            'actor' => $this->whenLoaded('user', fn () => $this->user?->name),
            'occurred_at' => $this->created_at?->toISOString(),
            'reason' => $this->reason,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'metadata' => $this->metadata,
        ];
    }
}
