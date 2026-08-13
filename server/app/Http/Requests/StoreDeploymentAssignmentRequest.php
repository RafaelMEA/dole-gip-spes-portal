<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentAssignmentRequest extends FormRequest
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
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'host_agency_id' => ['required', 'integer', 'exists:host_agencies,id'],
            'deployment_site_id' => ['nullable', 'integer', 'exists:deployment_sites,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['scheduled', 'active', 'completed', 'cancelled'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
