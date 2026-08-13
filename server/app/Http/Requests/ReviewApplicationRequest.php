<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewApplicationRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    private const ACTIONS = [
        'start_review',
        'request_documents',
        'approve',
        'reject',
        'schedule_deployment',
        'deploy',
        'complete',
    ];

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
            'action' => ['required', Rule::in(self::ACTIONS)],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:action,reject,request_documents',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'remarks.required_if' => 'A reason is required for this action.',
        ];
    }
}
