<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaff();
    }

    public function rules(): array
    {
        return [
            'deployment_slot_id' => ['required', 'integer', 'exists:deployment_slots,id'],
        ];
    }
}
