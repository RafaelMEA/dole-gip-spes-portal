<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'requirement' => $this->whenLoaded('requirement', fn () => $this->requirement?->name),
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'verification_status' => $this->verification_status->value,
            'verification_label' => $this->verification_status->label(),
            'rejection_reason' => $this->rejection_reason,
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'verified_at' => $this->verified_at?->toISOString(),
            'verified_by' => $this->whenLoaded('verifiedBy', fn () => $this->verifiedBy?->name),
            'download_url' => url('/api/documents/'.$this->id.'/download'),
        ];
    }
}
