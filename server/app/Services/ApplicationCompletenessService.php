<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Requirement;
use Illuminate\Support\Collection;

/**
 * Single source of truth for whether a student application is complete
 * enough to be submitted.
 *
 * The frontend may render the result for UX, but the backend re-runs these
 * checks at submission time; a client can never decide completeness itself.
 */
class ApplicationCompletenessService
{
    /**
     * The applicant-profile fields the system already treats as mandatory.
     * The keys map to actual schema columns; the values are the labels shown
     * to students. This mirrors UpdateStudentProfileRequest's required rules.
     *
     * @var array<string, string>
     */
    private const REQUIRED_PROFILE_FIELDS = [
        'name' => 'Full name',
        'school_name' => 'School name',
        'course' => 'Course',
        'year_level' => 'Year level',
    ];

    /**
     * A complete summary of the application's submission readiness.
     *
     * @return array{
     *   is_complete: bool,
     *   application_complete: bool,
     *   documents_complete: bool,
     *   missing_application_fields: array<int, string>,
     *   missing_requirements: array<int, array{id: int, name: string, is_required: bool}>,
     * }
     */
    public function summarize(Application $application): array
    {
        $missingApplicationFields = $this->missingApplicationFields($application);
        $missingRequirements = $this->missingRequirements($application);

        return [
            'is_complete' => $missingApplicationFields === [] && $missingRequirements === [],
            'application_complete' => $missingApplicationFields === [],
            'documents_complete' => $missingRequirements === [],
            'missing_application_fields' => array_values($missingApplicationFields),
            'missing_requirements' => array_values($missingRequirements),
        ];
    }

    /**
     * Whether the application passes every student-side completeness rule.
     */
    public function isComplete(Application $application): bool
    {
        return $this->missingApplicationFields($application) === []
            && $this->missingRequirements($application) === [];
    }

    /**
     * The human-readable labels of required application information that is
     * still missing or invalid on the applicant's profile.
     *
     * @return array<int, string>
     */
    public function missingApplicationFields(Application $application): array
    {
        $missing = [];

        $applicant = $application->applicant;
        $detail = $applicant?->studentDetail;

        if ($applicant === null || trim((string) $applicant->name) === '') {
            $missing[] = self::REQUIRED_PROFILE_FIELDS['name'];
        }

        if ($detail === null) {
            $missing = array_merge($missing, array_values(array_slice(self::REQUIRED_PROFILE_FIELDS, 1)));

            return array_values(array_unique($missing));
        }

        foreach (['school_name', 'course', 'year_level'] as $field) {
            $value = $detail->{$field};

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = self::REQUIRED_PROFILE_FIELDS[$field];
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * The required requirements of the application's cycle that do not have
     * a valid document attached to this application.
     *
     * Optional requirements never block submission and are never reported.
     *
     * @return array<int, array{id: int, name: string, is_required: bool}>
     */
    public function missingRequirements(Application $application): array
    {
        $cycle = $application->programCycle;

        if ($cycle === null) {
            return [];
        }

        $requirements = $cycle->requirements()->get();
        $documents = $application->documents()->get();

        return $requirements
            ->filter(fn (Requirement $requirement): bool => (bool) $requirement->pivot->is_required)
            ->reject(fn (Requirement $requirement): bool => $this->hasValidDocument($requirement, $documents))
            ->map(fn (Requirement $requirement): array => [
                'id' => $requirement->id,
                'name' => $requirement->name,
                'is_required' => true,
            ])
            ->values()
            ->all();
    }

    /**
     * Whether a document belonging to this application satisfies the
     * requirement. The document must exist and still obey the requirement's
     * file rules (allowed types / max size) so that rule changes or direct
     * database manipulation cannot fabricate a "valid" upload.
     *
     * @param  Collection<int, ApplicationDocument>  $documents
     */
    private function hasValidDocument(Requirement $requirement, Collection $documents): bool
    {
        $document = $documents->firstWhere('requirement_id', $requirement->id);

        return $document !== null && $this->documentSatisfies($document, $requirement);
    }

    /**
     * Check the stored document against the requirement's current file rules.
     */
    private function documentSatisfies(ApplicationDocument $document, Requirement $requirement): bool
    {
        $allowed = $requirement->allowed_file_types;

        if (is_array($allowed) && $allowed !== []) {
            $extension = strtolower((string) pathinfo((string) $document->file_path, PATHINFO_EXTENSION));
            $allowedTypes = array_map('strtolower', $allowed);

            if (! in_array($extension, $allowedTypes, true)) {
                return false;
            }
        }

        $max = $requirement->max_file_size;

        if ($max !== null && (int) $document->file_size > (int) $max) {
            return false;
        }

        return true;
    }
}
