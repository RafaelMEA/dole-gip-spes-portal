<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at?->toISOString(),
            'student_detail' => $this->whenLoaded('studentDetail', fn () => $this->studentDetail ? [
                'school_name' => $this->studentDetail->school_name,
                'course' => $this->studentDetail->course,
                'year_level' => $this->studentDetail->year_level,
            ] : null),
        ];
    }
}
