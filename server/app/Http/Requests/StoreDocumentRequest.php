<?php

namespace App\Http\Requests;

use App\Models\ApplicationDocument;
use App\Models\Requirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Defaults used when the upload is not tied to a requirement or the
     * requirement does not constrain file types/size.
     */
    private const DEFAULT_FILE_TYPES = ['pdf', 'jpg', 'jpeg', 'png'];

    private const DEFAULT_MAX_KB = 10240;

    public function authorize(): bool
    {
        $application = $this->route('application');

        return $this->user()->isStudent()
            && $application !== null
            && $this->user()->can('create', [ApplicationDocument::class, $application->id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $application = $this->route('application');

        $requirement = null;
        if ($this->input('requirement_id') !== null) {
            $requirement = Requirement::whereHas('programCycles', function ($query) use ($application) {
                $query->where('program_cycle_id', $application->program_cycle_id);
            })->find($this->input('requirement_id'));
        }

        $allowedTypes = $requirement?->allowed_file_types ?: self::DEFAULT_FILE_TYPES;
        $maxKb = $requirement?->max_file_size ?: self::DEFAULT_MAX_KB;

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:'.implode(',', $allowedTypes),
            ],
            'requirement_id' => [
                'nullable',
                'integer',
                Rule::exists('program_cycle_requirements', 'requirement_id')
                    ->where('program_cycle_id', $application->program_cycle_id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The document must not exceed :max kilobytes.',
            'file.mimes' => 'The document type is not accepted for this requirement.',
            'requirement_id.exists' => 'The selected document type is not part of this program.',
        ];
    }
}
