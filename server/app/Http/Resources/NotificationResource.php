<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A notification for the authenticated user's notification centre.
 *
 * Only display fields and navigation identifiers are exposed; whatever else
 * may sit inside the stored payload never leaves the API.
 *
 * @property DatabaseNotification $resource
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => data_get($this->data, 'type'),
            'title' => data_get($this->data, 'title'),
            'message' => data_get($this->data, 'message'),
            'action_url' => data_get($this->data, 'action_url'),
            'application_id' => data_get($this->data, 'application_id'),
            'assignment_id' => data_get($this->data, 'assignment_id'),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
