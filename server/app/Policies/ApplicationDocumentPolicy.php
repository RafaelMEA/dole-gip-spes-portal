<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Models\ApplicationDocument;
use App\Models\User;

class ApplicationDocumentPolicy
{
    public function view(User $user, ApplicationDocument $document): bool
    {
        return $user->isStaff() || $user->id === $document->application->applicant_id;
    }

    /**
     * Students may upload documents to an application that is still in a
     * student-editable state. Once an application has been submitted it is
     * immutable from the student's perspective: no new uploads and no
     * replacements are allowed.
     */
    public function create(User $user, ?int $applicationId = null): bool
    {
        if (! $user->isStudent()) {
            return false;
        }

        if ($applicationId === null) {
            return true;
        }

        $application = \App\Models\Application::find($applicationId);

        return $application !== null
            && $application->applicant_id === $user->id
            && in_array($application->status, [
                ApplicationStatus::Draft,
                ApplicationStatus::ReturnedForCorrection,
            ], true);
    }

    /**
     * Staff verify/reject documents, but only while the application is in a
     * staff-reviewable state.
     *
     * Draft applications are entirely student-controlled, so their documents
     * can never be decided on. Documents for applications that have already
     * been approved (or moved past review into deployment/completion) are no
     * longer subject to verification either. Applications returned to the
     * student for more documents (documents_incomplete) are also excluded:
     * any replacements uploaded there stay pending until the student submits
     * the application again.
     */
    public function verify(User $user, ApplicationDocument $document): bool
    {
        return $user->isStaff()
            && in_array($document->application->status, self::staffReviewableStatuses(), true);
    }

    /**
     * The application statuses in which a document may be verified or
     * rejected.
     *
     * @return array<int, ApplicationStatus>
     */
    private static function staffReviewableStatuses(): array
    {
        return [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderReview,
            ApplicationStatus::DocumentsVerified,
        ];
    }

    /**
     * The applicant may remove their own unverified documents.
     */
    public function delete(User $user, ApplicationDocument $document): bool
    {
        return $user->id === $document->application->applicant_id
            && in_array($document->verification_status->value, ['pending', 'rejected'], true)
            && in_array($document->application->status, [
                ApplicationStatus::Draft,
                ApplicationStatus::ReturnedForCorrection,
            ], true);
    }
}
