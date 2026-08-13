<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
            'allowed_file_types' => $this->allowed_file_types,
            'max_file_size' => $this->max_file_size,
            'is_required' => $this->when(isset($this->pivot), fn () => (bool) $this->pivot->is_required),
        ];
    }
}
