<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentSiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'host_agency_id' => $this->host_agency_id,
            'host_agency' => $this->whenLoaded('hostAgency', fn () => new HostAgencyResource($this->hostAgency)),
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
