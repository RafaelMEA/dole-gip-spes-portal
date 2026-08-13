<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequirementRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'allowed_file_types' => ['nullable', 'array'],
            'allowed_file_types.*' => ['string', 'regex:/^[a-zA-Z0-9]+$/'],
            'max_file_size' => ['nullable', 'integer', 'min:1'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('requirements', 'slug')->ignore($this->route('requirement'))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
