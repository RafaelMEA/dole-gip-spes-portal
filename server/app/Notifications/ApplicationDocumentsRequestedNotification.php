<?php

namespace App\Notifications;

use App\Models\Application;

/**
 * Sent to the applicant when staff request additional or corrected documents.
 * The staff remarks explain what is missing; truncated for display.
 */
class ApplicationDocumentsRequestedNotification extends ApplicationWorkflowNotification
{
    public function __construct(
        Application $application,
        protected readonly ?string $remarks = null,
    ) {
        parent::__construct($application);
    }

    protected function type(): string
    {
        return 'application.documents_requested';
    }

    protected function titleAndMessage(string $audience): array
    {
        return [
            'Additional Documents Required',
            $this->actionRequiredMessage(),
        ];
    }

    private function actionRequiredMessage(): string
    {
        $reason = trim((string) $this->remarks);

        if ($reason !== '') {
            return 'Action required: '.str($reason)->limit(160);
        }

        return 'Action required: additional documents are needed. See details.';
    }
}
