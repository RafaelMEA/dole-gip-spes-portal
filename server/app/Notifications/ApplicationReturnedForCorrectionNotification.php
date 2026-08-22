<?php

namespace App\Notifications;

use App\Models\Application;

/**
 * Sent to the applicant when staff return their application for correction.
 * The staff remarks are the same correction instructions the student already
 * sees on the application page; they are truncated for notification display.
 */
class ApplicationReturnedForCorrectionNotification extends ApplicationWorkflowNotification
{
    public function __construct(
        Application $application,
        protected readonly ?string $remarks = null,
    ) {
        parent::__construct($application);
    }

    protected function type(): string
    {
        return 'application.returned_for_correction';
    }

    protected function titleAndMessage(string $audience): array
    {
        return [
            'Application Returned for Correction',
            $this->actionRequiredMessage(),
        ];
    }

    private function actionRequiredMessage(): string
    {
        $reason = trim((string) $this->remarks);

        if ($reason !== '') {
            return 'Action required: '.str($reason)->limit(160);
        }

        return 'Action required: your application was returned for correction. See details.';
    }
}
