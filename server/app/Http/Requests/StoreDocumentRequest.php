<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $this->user()->isStudent()
            && $application !== null
            && $this->user()->can('create', [\App\Models\ApplicationDocument::class, $application->id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'requirement_id' => [
                'nullable',
                'integer',
                'exists:requirements,id',
                Rule::unique('application_documents', 'requirement_id')->where(function ($query) {
                    return $query->where('application_id', $this->route('application')->id);
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The document must not exceed 10MB.',
            'file.mimes' => 'The document must be a PDF, JPG, or PNG file.',
            'requirement_id.unique' => 'A document for this requirement has already been uploaded.',
        ];
    }
}
