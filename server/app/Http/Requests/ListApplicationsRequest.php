<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListApplicationsRequest extends FormRequest
{
    /**
     * Only staff may query the application management endpoint. The route is
     * also guarded by the role middleware, so this is defence in depth.
     */
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = collect(ApplicationStatus::cases())
            ->map(fn (ApplicationStatus $status) => $status->value)
            ->push('all')
            ->all();

        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::in($statuses)],
            'program_id' => ['sometimes', 'nullable', 'integer', 'exists:programs,id'],
            'program_cycle_id' => ['sometimes', 'nullable', 'integer', 'exists:program_cycles,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => [
                'sometimes',
                'nullable',
                'date',
                Rule::when($this->filled('submitted_from'), 'after_or_equal:submitted_from'),
            ],
            'sort' => ['sometimes', 'nullable', 'string', Rule::in(['submitted_at', 'created_at', 'updated_at'])],
            'direction' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', Rule::in([10, 20, 50])],
        ];
    }
}
