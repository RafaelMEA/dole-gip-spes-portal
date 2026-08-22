<?php

namespace App\Events;

use App\Models\DeploymentAssignment;
use App\Models\User;

/**
 * Staff assigned a student to a deployment slot.
 */
class DeploymentAssignmentCreated
{
    public function __construct(
        public readonly DeploymentAssignment $assignment,
        public readonly User $actor,
    ) {}
}
