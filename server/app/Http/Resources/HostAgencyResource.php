<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostAgencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'active_assignments' => $this->active_assignments_count ?? 0,
        ];
    }
}
