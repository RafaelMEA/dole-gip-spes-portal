<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'remarks' => $this->remarks,
            'decision_reason' => $this->decision_reason,
            'decided_at' => $this->decided_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'missing_required_documents' => $this->when(
                $this->relationLoaded('documents') && $this->relationLoaded('programCycle'),
                fn () => app(\App\Services\ApplicationService::class)->missingRequiredDocuments($this->resource),
                [],
            ),
            'program_cycle' => $this->whenLoaded('programCycle', fn () => new ProgramCycleResource($this->programCycle)),
            'documents' => ApplicationDocumentResource::collection($this->whenLoaded('documents')),
            'status_history' => ApplicationStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'assignment' => $this->whenLoaded('deploymentAssignment', fn () => new DeploymentAssignmentResource($this->deploymentAssignment)),
            'applicant' => $this->whenLoaded('applicant', fn () => new UserResource($this->applicant)),
            'decided_by' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy?->name),
        ];
    }
}
