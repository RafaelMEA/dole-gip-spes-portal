<?php

namespace App\Http\Requests;

use App\Enums\DocumentVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaff();
    }

    /**
     * Staff may only flip a document to verified or rejected through this
     * action. Pending is not accepted here: documents start as pending when
     * uploaded and there is no reset-to-pending workflow.
     *
     * A rejection must carry a meaningful reason (at least 10 characters and
     * not only whitespace, at most 1000). When the document is verified the
     * rejection reason must be left empty so a verified record can never hold
     * a reason.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verification_status' => [
                'required',
                Rule::enum(DocumentVerificationStatus::class)->only([
                    DocumentVerificationStatus::Verified,
                    DocumentVerificationStatus::Rejected,
                ]),
            ],
            'rejection_reason' => [
                'nullable',
                'string',
                'min:10',
                'max:1000',
                'regex:/[^\s]/',
                'required_if:verification_status,rejected',
                'prohibited_if:verification_status,verified',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'A clear reason is required when rejecting a document.',
            'rejection_reason.min' => 'The rejection reason must be at least :min characters.',
            'rejection_reason.max' => 'The rejection reason must not exceed :max characters.',
            'rejection_reason.regex' => 'The rejection reason must contain meaningful text.',
            'rejection_reason.prohibited' => 'A rejection reason cannot be provided when verifying a document.',
        ];
    }
}
