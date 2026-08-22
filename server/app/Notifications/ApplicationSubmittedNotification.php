<?php

namespace App\Notifications;

/**
 * Fired when a student submits (or resubmits) an application.
 *
 * The applicant gets a confirmation; every staff reviewer is informed that a
 * new/corrected application entered the review queue. Messages deliberately
 * avoid student names or profile details.
 */
class ApplicationSubmittedNotification extends ApplicationWorkflowNotification
{
    protected function type(): string
    {
        return 'application.submitted';
    }

    protected function titleAndMessage(string $audience): array
    {
        $label = $this->programLabel();

        if ($audience === 'staff') {
            return [
                'New Application Submitted',
                "A new {$label} application has been submitted for review.",
            ];
        }

        return [
            'Application Submitted',
            "Your {$label} application has been submitted for review.",
        ];
    }
}
