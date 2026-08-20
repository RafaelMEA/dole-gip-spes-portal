<?php

namespace App\Policies;

use App\Models\DeploymentAssignment;
use App\Models\User;

class DeploymentAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, DeploymentAssignment $assignment): bool
    {
        return $user->isStaff()
            || $user->id === $assignment->application->applicant_id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function cancel(User $user, DeploymentAssignment $assignment): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, DeploymentAssignment $assignment): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, DeploymentAssignment $assignment): bool
    {
        return $user->isStaff();
    }
}
