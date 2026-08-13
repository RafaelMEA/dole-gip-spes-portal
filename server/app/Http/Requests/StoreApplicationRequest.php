<?php

namespace App\Http\Requests;

use App\Models\ProgramCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
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
            'program_cycle_id' => [
                'required',
                'integer',
                'exists:program_cycles,id',
                Rule::unique('applications', 'program_cycle_id')->where(function ($query) {
                    return $query->where('applicant_id', $this->user()->id);
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
            'program_cycle_id.unique' => 'You already have an application for this program cycle.',
        ];
    }

    /**
     * The cycle must be open for applications.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $cycle = ProgramCycle::find($this->input('program_cycle_id'));

                if ($cycle && ! $cycle->isAcceptingApplications()) {
                    $validator->errors()->add(
                        'program_cycle_id',
                        'This program cycle is not accepting applications.',
                    );
                }
            },
        ];
    }
}
