<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_cycle_id' => $this->program_cycle_id,
            'deployment_site_id' => $this->deployment_site_id,
            'title' => $this->title,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'assigned_count' => $this->assigned_count,
            'available_count' => $this->available_count,
            'status' => $this->storedStatus()->value,
            'status_label' => $this->storedStatus()->label(),
            'program_cycle' => $this->whenLoaded('programCycle', fn () => new ProgramCycleResource($this->programCycle)),
            'deployment_site' => $this->whenLoaded('deploymentSite', fn () => new DeploymentSiteResource($this->deploymentSite)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
