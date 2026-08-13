<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStudent();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'school_name' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'year_level' => ['required', Rule::in(['1', '2', '3', '4'])],
            'gwa' => ['nullable', 'numeric', 'min:1.00', 'max:5.00'],
            'address' => ['nullable', 'string', 'max:500'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', Rule::in(['male', 'female'])],
            'is_indigent' => ['nullable', 'boolean'],
            'is_4ps_member' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthdate.before' => 'The birthdate must be in the past.',
            'gwa.between' => 'The GWA must be between 1.00 and 5.00.',
        ];
    }
}
