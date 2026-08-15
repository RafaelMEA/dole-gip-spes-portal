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
                ApplicationStatus::DocumentsIncomplete,
            ], true);
    }

    /**
     * Staff verify/reject documents.
     */
    public function verify(User $user, ApplicationDocument $document): bool
    {
        return $user->isStaff();
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
                ApplicationStatus::DocumentsIncomplete,
            ], true);
    }
}
