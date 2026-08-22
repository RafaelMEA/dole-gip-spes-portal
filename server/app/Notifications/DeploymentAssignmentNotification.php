<?php

namespace App\Notifications;

use App\Models\DeploymentAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Base class for deployment-assignment notifications delivered to the
 * assigned student through the database channel.
 *
 * The payload carries only identifiers and display strings. The action URL
 * points at the student's application page, where the assignment is shown.
 */
abstract class DeploymentAssignmentNotification extends Notification
{
    use Queueable;

    public function __construct(protected readonly DeploymentAssignment $assignment) {}

    /**
     * Only in-app delivery for now; future channels append here or arrive as
     * additional queued listeners on the domain event.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        [$title, $message] = $this->titleAndMessage();
        $applicationId = $this->assignment->application_id;

        return [
            'type' => $this->type(),
            'title' => $title,
            'message' => $message,
            'action_url' => '/student/applications/'.$applicationId,
            'application_id' => $applicationId,
            'assignment_id' => $this->assignment->getKey(),
        ];
    }

    /**
     * @return array{0: string, 1: string} [title, message]
     */
    abstract protected function titleAndMessage(): array;

    /**
     * The machine-readable notification type.
     */
    abstract protected function type(): string;

    /**
     * The host agency display name, if still resolvable.
     */
    protected function agencyName(): ?string
    {
        return $this->assignment->hostAgency()->value('name');
    }
}
