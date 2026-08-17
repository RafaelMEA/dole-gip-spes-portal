<?php

namespace App\Policies;

use App\Models\HostAgency;
use App\Models\User;

class HostAgencyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HostAgency $agency): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, HostAgency $agency): bool
    {
        return $user->isStaff();
    }

    public function manage(User $user, HostAgency $agency): bool
    {
        return $user->isStaff();
    }
}
