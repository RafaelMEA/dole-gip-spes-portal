<?php

namespace App\Policies;

use App\Models\DeploymentSlot;
use App\Models\User;

class DeploymentSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, DeploymentSlot $slot): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, DeploymentSlot $slot): bool
    {
        return $user->isStaff();
    }

    public function changeStatus(User $user, DeploymentSlot $slot): bool
    {
        return $user->isStaff();
    }
}
