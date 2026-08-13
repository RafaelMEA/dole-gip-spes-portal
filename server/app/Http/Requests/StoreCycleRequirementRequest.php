<?php

namespace App\Http\Requests;

use App\Models\ProgramCycleRequirement;
use Closure;
use Illuminate\Validation\Rule;

class StoreCycleRequirementRequest extends StoreRequirementRequest
{
    /**
     * Supports two modes:
     *
     * 1. Attach an existing catalog requirement:
     *    { "requirement_id": 5, "is_required": true }
     *
     * 2. Create a new requirement and attach it to the cycle:
     *    { "name": "...", "slug": "...", "description": "...",
     *      "is_required": true, "allowed_file_types": ["pdf"], "max_file_size": 5120 }
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->filled('requirement_id')) {
            return [
                'requirement_id' => ['required', 'integer', 'exists:requirements,id'],
                'is_required' => ['nullable', 'boolean'],
            ];
        }

        return array_merge(parent::rules(), [
            'is_required' => ['nullable', 'boolean'],
            'name' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, Closure $fail) {
                    $cycleId = $this->route('cycle')->id;
                    $ignoreId = $this->route('requirement')?->id;

                    $exists = ProgramCycleRequirement::query()
                        ->where('program_cycle_id', $cycleId)
                        ->whereHas('requirement', fn ($q) => $q
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                        )
                        ->when($ignoreId, fn ($q) => $q->where('requirement_id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('A requirement with this name already exists for this cycle.');
                    }
                },
            ],
        ]);
    }
}
