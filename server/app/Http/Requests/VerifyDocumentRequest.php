<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDocumentRequest extends FormRequest
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
            'verification_status' => ['required', Rule::in(['verified', 'rejected'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000', 'required_if:verification_status,rejected'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'A reason is required when rejecting a document.',
        ];
    }
}
