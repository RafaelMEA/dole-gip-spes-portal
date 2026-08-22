<?php

namespace App\Notifications;

/**
 * Sent to the applicant when their application is rejected. The message stays
 * neutral; the recorded decision reason remains on the application detail
 * page rather than being broadcast in notification data.
 */
class ApplicationRejectedNotification extends ApplicationWorkflowNotification
{
    protected function type(): string
    {
        return 'application.rejected';
    }

    protected function titleAndMessage(string $audience): array
    {
        $label = $this->programLabel();

        return [
            'Application Rejected',
            "Your {$label} application was not approved. See details for the reason.",
        ];
    }
}
