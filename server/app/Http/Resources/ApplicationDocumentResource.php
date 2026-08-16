<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'requirement_id' => $this->requirement_id,
            'requirement' => $this->whenLoaded('requirement', fn () => $this->requirement
                ? [
                    'id' => $this->requirement->id,
                    'name' => $this->requirement->name,
                    'description' => $this->requirement->description,
                    'allowed_file_types' => $this->requirement->allowed_file_types,
                    'max_file_size' => $this->requirement->max_file_size,
                ]
                : null),
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'verification_status' => $this->verification_status->value,
            'verification_label' => $this->verification_status->label(),
            'rejection_reason' => $this->rejection_reason,
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'verified_at' => $this->verified_at?->toISOString(),
            // The verifying staff member's name is only relevant to staff;
            // students do not need to know which officer made the decision.
            'verified_by' => $this->when(
                $request->user()?->isStaff() && $this->relationLoaded('verifiedBy'),
                fn () => $this->verifiedBy?->name,
            ),
            'view_url' => url('/api/documents/'.$this->id.'/download?disposition=inline'),
            'download_url' => url('/api/documents/'.$this->id.'/download'),
        ];
    }
}
