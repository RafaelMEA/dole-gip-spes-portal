<?php

namespace App\Notifications;

/**
 * Fired when a student resubmits a corrected application.
 *
 * The applicant gets a confirmation; staff reviewers learn the application is
 * back in their queue. No student-identifying details are included.
 */
class ApplicationResubmittedNotification extends ApplicationWorkflowNotification
{
    protected function type(): string
    {
        return 'application.resubmitted';
    }

    protected function titleAndMessage(string $audience): array
    {
        $label = $this->programLabel();

        if ($audience === 'staff') {
            return [
                'Application Resubmitted',
                "A corrected {$label} application has been resubmitted for review.",
            ];
        }

        return [
            'Application Resubmitted',
            "Your corrected {$label} application has been resubmitted for review.",
        ];
    }
}
