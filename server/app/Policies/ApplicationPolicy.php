<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Staff may view every application; students may view their own.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isStudent();
    }

    public function view(User $user, Application $application): bool
    {
        return $user->isStaff() || $user->id === $application->applicant_id;
    }

    public function create(User $user): bool
    {
        return $user->isStudent();
    }

    public function update(User $user, Application $application): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->id === $application->applicant_id
            && in_array($application->status, [
                ApplicationStatus::Draft,
                ApplicationStatus::DocumentsIncomplete,
            ], true);
    }

    public function delete(User $user, Application $application): bool
    {
        return $user->id === $application->applicant_id
            && $application->status === ApplicationStatus::Draft;
    }

    /**
     * Students may submit their own draft applications.
     */
    public function submit(User $user, Application $application): bool
    {
        return $user->id === $application->applicant_id
            && $application->status === ApplicationStatus::Draft;
    }

    public function withdraw(User $user, Application $application): bool
    {
        return $user->id === $application->applicant_id
            && in_array($application->status, [
                ApplicationStatus::Draft,
                ApplicationStatus::Submitted,
            ], true);
    }

    public function resubmit(User $user, Application $application): bool
    {
        return $user->id === $application->applicant_id
            && $application->status === ApplicationStatus::DocumentsIncomplete;
    }

    /**
     * Staff-only review actions.
     */
    public function review(User $user, Application $application): bool
    {
        return $user->isStaff();
    }
}
