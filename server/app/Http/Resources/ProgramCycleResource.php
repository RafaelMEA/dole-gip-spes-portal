<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_id' => $this->program_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_published' => $this->isPublished(),
            'is_accepting_applications' => $this->isAcceptingApplications(),
            'total_slots' => $this->total_slots,
            'slots_remaining' => $this->slots_remaining,
            'application_start' => $this->application_start?->toDateString(),
            'application_deadline' => $this->application_deadline?->toDateString(),
            'deployment_start' => $this->deployment_start?->toDateString(),
            'deployment_end' => $this->deployment_end?->toDateString(),
            'program' => $this->whenLoaded('program', fn () => new ProgramResource($this->program)),
            'requirements' => RequirementResource::collection($this->whenLoaded('requirements')),
        ];
    }
}
