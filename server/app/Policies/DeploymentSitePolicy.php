<?php

namespace App\Policies;

use App\Models\DeploymentSite;
use App\Models\User;

class DeploymentSitePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DeploymentSite $site): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, DeploymentSite $site): bool
    {
        return $user->isStaff();
    }

    public function manage(User $user, DeploymentSite $site): bool
    {
        return $user->isStaff();
    }
}
