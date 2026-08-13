<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->studentDetail;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'student_details' => $detail ? [
                'school_name' => $detail->school_name,
                'course' => $detail->course,
                'year_level' => $detail->year_level !== null ? (string) $detail->year_level : null,
                'gwa' => $detail->gwa !== null ? (float) $detail->gwa : null,
                'address' => $detail->address,
                'birthplace' => $detail->birthplace,
                'birthdate' => $detail->birthdate?->toDateString(),
                'sex' => $detail->sex,
                'is_indigent' => (bool) $detail->is_indigent,
                'is_4ps_member' => (bool) $detail->is_4ps_member,
            ] : null,
        ];
    }
}
