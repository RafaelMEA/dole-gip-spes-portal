<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Exceptions\IncompleteApplicationException;
use App\Models\Application;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ApplicationService
{
    public function __construct(
        private readonly ApplicationCompletenessService $completeness,
    ) {
    }

    /**
     * The state machine: [from => [to, ...]].
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'draft' => ['submitted', 'withdrawn'],
        'submitted' => ['under_review', 'rejected', 'withdrawn'],
        'under_review' => ['documents_incomplete', 'approved', 'rejected'],
        'documents_incomplete' => ['submitted', 'approved', 'rejected'],
        'approved' => ['for_deployment'],
        'for_deployment' => ['deployed'],
        'deployed' => ['completed'],
        'rejected' => [],
        'withdrawn' => [],
        'completed' => [],
    ];

    /**
     * The transitions the student may trigger themselves.
     *
     * @var array<int, string>
     */
    private const STUDENT_TRANSITIONS = ['submit', 'withdraw', 'resubmit'];

    /**
     * The current status the given user may move the application from.
     */
    public function canTransition(Application $application, User $user, string $action): bool
    {
        $from = $application->status->value;

        if (! $this->isStudentAction($action) && ! $user->isStaff()) {
            return false;
        }

        if ($this->isStudentAction($action) && $user->id !== $application->applicant_id) {
            return false;
        }

        if ($this->isStudentAction($action) && ! $this->isStudentControlledFrom($from)) {
            return false;
        }

        $actionTo = match ($action) {
            'submit' => ApplicationStatus::Submitted->value,
            'withdraw' => ApplicationStatus::Withdrawn->value,
            'resubmit' => ApplicationStatus::Submitted->value,
            'start_review' => ApplicationStatus::UnderReview->value,
            'request_documents' => ApplicationStatus::DocumentsIncomplete->value,
            'approve' => ApplicationStatus::Approved->value,
            'reject' => ApplicationStatus::Rejected->value,
            'schedule_deployment' => ApplicationStatus::ForDeployment->value,
            'deploy' => ApplicationStatus::Deployed->value,
            'complete' => ApplicationStatus::Completed->value,
            default => null,
        };

        if ($actionTo === null) {
            return false;
        }

        return in_array($actionTo, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Apply a status transition and record it in the history.
     */
    public function transition(
        Application $application,
        User $user,
        string $action,
        ?string $remarks = null,
    ): Application {
        if (! $this->canTransition($application, $user, $action)) {
            throw new DomainException(
                "The application cannot move from \"{$application->status->value}\" via \"$action\".",
            );
        }

        return DB::transaction(function () use ($application, $user, $action, $remarks) {
            $this->apply($application, $user, $action);

            $application->statusHistory()->create([
                'status' => $application->status,
                'changed_by' => $user->id,
                'remarks' => $remarks,
                'changed_at' => now(),
            ]);

            return $application;
        });
    }

    /**
     * Convenience wrappers used by controllers.
     */
    public function submit(Application $application, User $user, ?string $remarks = null): Application
    {
        if (! $this->canTransition($application, $user, 'submit')) {
            throw new DomainException(
                "The application cannot move from \"{$application->status->value}\" via \"submit\".",
            );
        }

        $this->assertEligibleForSubmission($application);

        return $this->transition($application, $user, 'submit', $remarks);
    }

    public function resubmit(Application $application, User $user, ?string $remarks = null): Application
    {
        return $this->transition($application, $user, 'resubmit', $remarks);
    }

    public function withdraw(Application $application, User $user, ?string $remarks = null): Application
    {
        return $this->transition($application, $user, 'withdraw', $remarks);
    }

    public function startReview(Application $application, User $user, ?string $remarks = null): Application
    {
        return $this->transition($application, $user, 'start_review', $remarks);
    }

    public function requestDocuments(Application $application, User $user, string $remarks): Application
    {
        if (trim($remarks) === '') {
            throw new DomainException('A reason is required before requesting additional documents.');
        }

        return $this->transition($application, $user, 'request_documents', $remarks);
    }

    public function approve(Application $application, User $user, ?string $remarks = null): Application
    {
        $application = $this->transition($application, $user, 'approve', $remarks);

        $application->forceFill([
            'approved_at' => now(),
            'approved_by' => $user->id,
        ])->save();

        return $application;
    }

    public function reject(Application $application, User $user, string $remarks): Application
    {
        if (trim($remarks) === '') {
            throw new DomainException('A reason is required before rejecting an application.');
        }

        return $this->transition($application, $user, 'reject', $remarks);
    }

    public function scheduleForDeployment(Application $application, User $user, ?string $remarks = null): Application
    {
        return $this->transition($application, $user, 'schedule_deployment', $remarks);
    }

    public function deploy(Application $application, User $user, ?string $remarks = null): Application
    {
        return $this->transition($application, $user, 'deploy', $remarks);
    }

    public function complete(Application $application, User $user, ?string $remarks = null): Application
    {
        return $this->transition($application, $user, 'complete', $remarks);
    }

    /**
     * Verify the application can still be submitted right now:
     *
     * 1. The program cycle must still be accepting applications. The backend
     *    is authoritative here; a cycle that has closed (or was never
     *    published) blocks submission even if the application is still a
     *    draft.
     * 2. The application must be complete (required profile information and
     *    every required document present and valid). Optional documents never
     *    block submission.
     *
     * These checks are re-run on every submission request; a prior
     * completeness GET is never trusted.
     */
    private function assertEligibleForSubmission(Application $application): void
    {
        if (! $application->programCycle->isAcceptingApplications()) {
            throw new DomainException('The application period for this program has closed.');
        }

        $missingApplicationFields = $this->completeness->missingApplicationFields($application);
        $missingRequirements = $this->completeness->missingRequirements($application);

        if ($missingApplicationFields === [] && $missingRequirements === []) {
            return;
        }

        throw new IncompleteApplicationException(
            missingApplicationFields: $missingApplicationFields,
            missingRequirements: $missingRequirements,
        );
    }

    /**
     * All required documents for this application's cycle that are not yet
     * verified. Empty array means the application is document-complete.
     *
     * @return array<int, string>
     */
    public function missingRequiredDocuments(Application $application): array
    {
        $requirements = $application->programCycle
            ->requirements()
            ->wherePivot('is_required', true)
            ->get();

        $verified = $application->documents()
            ->where('verification_status', 'verified')
            ->pluck('requirement_id')
            ->filter()
            ->all();

        return $requirements
            ->reject(fn ($requirement) => in_array($requirement->id, $verified, true))
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Apply the side effects of the transition on the model.
     */
    private function apply(Application $application, User $user, string $action): void
    {
        $status = match ($action) {
            'submit', 'resubmit' => ApplicationStatus::Submitted,
            'withdraw' => ApplicationStatus::Withdrawn,
            'start_review' => ApplicationStatus::UnderReview,
            'request_documents' => ApplicationStatus::DocumentsIncomplete,
            'approve' => ApplicationStatus::Approved,
            'reject' => ApplicationStatus::Rejected,
            'schedule_deployment' => ApplicationStatus::ForDeployment,
            'deploy' => ApplicationStatus::Deployed,
            'complete' => ApplicationStatus::Completed,
            default => throw new DomainException("Unknown transition action \"$action\"."),
        };

        $application->status = $status;

        if ($status === ApplicationStatus::Submitted) {
            $application->submitted_at = now();
        }

        $application->save();
    }

    private function isStudentAction(string $action): bool
    {
        return in_array($action, self::STUDENT_TRANSITIONS, true);
    }

    private function isStudentControlledFrom(string $from): bool
    {
        return in_array($from, [
            ApplicationStatus::Draft->value,
            ApplicationStatus::Submitted->value,
            ApplicationStatus::DocumentsIncomplete->value,
        ], true);
    }
}
