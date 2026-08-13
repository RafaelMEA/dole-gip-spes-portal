<?php

namespace App\Http\Requests;

use App\Enums\ProgramCycleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramCycleRequest extends FormRequest
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
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(ProgramCycleStatus::class)],
            'total_slots' => ['required', 'integer', 'min:1'],
            'application_start' => ['required', 'date'],
            'application_deadline' => ['required', 'date', 'after_or_equal:application_start'],
            'deployment_start' => ['nullable', 'date', 'after_or_equal:application_deadline'],
            'deployment_end' => ['nullable', 'date', 'after_or_equal:deployment_start'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['integer', 'exists:requirements,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'deployment_start.after_or_equal' => 'Deployment must start on or after the application deadline.',
            'deployment_end.after_or_equal' => 'The deployment end date must be on or after the deployment start date.',
        ];
    }
}
