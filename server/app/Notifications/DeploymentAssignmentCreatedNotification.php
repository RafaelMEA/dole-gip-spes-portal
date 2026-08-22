<?php

namespace App\Notifications;

/**
 * Sent to the student when staff assign them to a deployment slot.
 */
class DeploymentAssignmentCreatedNotification extends DeploymentAssignmentNotification
{
    protected function type(): string
    {
        return 'deployment.assigned';
    }

    protected function titleAndMessage(): array
    {
        $agency = $this->agencyName();
        $position = $this->assignment->position;

        $where = $agency !== null
            ? ($position !== null ? "as {$position} at {$agency}" : "at {$agency}")
            : 'for your program';

        return [
            'Deployment Assignment Created',
            "You have been assigned to a deployment {$where}. See details.",
        ];
    }
}
