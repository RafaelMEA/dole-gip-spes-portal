<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Program $program): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Program $program): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Program $program): bool
    {
        return $user->isStaff();
    }
}
