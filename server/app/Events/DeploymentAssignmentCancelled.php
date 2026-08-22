<?php

namespace App\Events;

use App\Models\DeploymentAssignment;
use App\Models\User;

/**
 * Staff cancelled a deployment assignment.
 */
class DeploymentAssignmentCancelled
{
    public function __construct(
        public readonly DeploymentAssignment $assignment,
        public readonly User $actor,
    ) {}
}
