<?php

namespace App\Notifications;

/**
 * Sent to the student when staff cancel their deployment assignment.
 */
class DeploymentAssignmentCancelledNotification extends DeploymentAssignmentNotification
{
    protected function type(): string
    {
        return 'deployment.cancelled';
    }

    protected function titleAndMessage(): array
    {
        $agency = $this->agencyName();

        $where = $agency !== null ? " at {$agency}" : '';

        return [
            'Deployment Assignment Cancelled',
            "Your deployment assignment{$where} has been cancelled. See details.",
        ];
    }
}
