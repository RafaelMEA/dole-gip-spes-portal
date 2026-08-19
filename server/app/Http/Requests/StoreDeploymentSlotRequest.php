<?php

namespace App\Http\Requests;

use App\Models\DeploymentSite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentSlotRequest extends FormRequest
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
            'program_cycle_id' => ['required', 'integer', 'exists:program_cycles,id'],
            'deployment_site_id' => ['required', 'integer', 'exists:deployment_sites,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
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
