<?php

namespace App\Policies;

use App\Models\ProgramCycle;
use App\Models\User;

class ProgramCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProgramCycle $cycle): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, ProgramCycle $cycle): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, ProgramCycle $cycle): bool
    {
        return $user->isStaff();
    }

    /**
     * Whether a student may open an application for this cycle.
     */
    public function apply(User $user, ProgramCycle $cycle): bool
    {
        return $user->isStudent() && $cycle->isAcceptingApplications();
    }
}
