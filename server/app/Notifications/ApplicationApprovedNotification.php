<?php

namespace App\Notifications;

/**
 * Sent to the applicant when their application is approved.
 */
class ApplicationApprovedNotification extends ApplicationWorkflowNotification
{
    protected function type(): string
    {
        return 'application.approved';
    }

    protected function titleAndMessage(string $audience): array
    {
        $label = $this->programLabel();

        return [
            'Application Approved',
            "Congratulations! Your {$label} application has been approved.",
        ];
    }
}
