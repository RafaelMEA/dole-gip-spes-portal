<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'position' => $this->position,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'remarks' => $this->remarks,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'host_agency' => $this->whenLoaded('hostAgency', fn () => new HostAgencyResource($this->hostAgency)),
            'deployment_site' => $this->whenLoaded('deploymentSite', fn () => new DeploymentSiteResource($this->deploymentSite)),
            'applicant' => $this->whenLoaded('application.applicant', fn () => new UserResource($this->application->applicant)),
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => $this->assignedBy?->name),
        ];
    }
}
