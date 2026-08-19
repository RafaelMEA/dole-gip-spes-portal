<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeploymentSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaff();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'deployment_site_id' => ['sometimes', 'required', 'integer', 'exists:deployment_sites,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'capacity.min' => 'Capacity must be at least 1.',
            'capacity.integer' => 'Capacity must be a whole number.',
        ];
    }
}
