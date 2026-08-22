<?php

namespace App\Listeners;

use App\Events\ApplicationApproved;
use App\Events\ApplicationDocumentsRequested;
use App\Events\ApplicationRejected;
use App\Events\ApplicationResubmitted;
use App\Events\ApplicationReturnedForCorrection;
use App\Events\ApplicationSubmitted;
use App\Events\DeploymentAssignmentCancelled;
use App\Events\DeploymentAssignmentCreated;
use App\Models\User;
use App\Notifications\ApplicationApprovedNotification;
use App\Notifications\ApplicationDocumentsRequestedNotification;
use App\Notifications\ApplicationRejectedNotification;
use App\Notifications\ApplicationResubmittedNotification;
use App\Notifications\ApplicationReturnedForCorrectionNotification;
use App\Notifications\ApplicationSubmittedNotification;
use App\Notifications\DeploymentAssignmentCancelledNotification;
use App\Notifications\DeploymentAssignmentCreatedNotification;
use Illuminate\Contracts\Events\ShouldBeDiscovered;
use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Notification as IlluminateNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * The single authoritative bridge between workflow events and in-app
 * notifications.
 *
 * Events are dispatched from inside the workflow transactions (application
 * transitions, deployment assignment create/cancel). Because this listener
 * runs synchronously at dispatch time, every notification it creates commits
 * or rolls back together with the state change that caused it — a failed
 * approval can never leave behind an "approved" notification.
 *
 * Registration is explicit: Event::subscribe() in AppServiceProvider. The
 * class opts out of Laravel's automatic event discovery so it can never be
 * registered twice (which would duplicate every notification).
 *
 * Future channels: add another listener (queued + afterCommit for mail/SMS/
 * broadcast) on these same events; nothing here changes.
 */
class WorkflowNotificationSubscriber implements ShouldBeDiscovered
{
    /**
     * Automatic discovery must skip this class; registration happens through
     * subscribe(), called from AppServiceProvider.
     */
    public static function shouldBeDiscovered(): bool
    {
        return false;
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ApplicationSubmitted::class, [self::class, 'handleApplicationSubmitted']);
        $events->listen(ApplicationResubmitted::class, [self::class, 'handleApplicationResubmitted']);
        $events->listen(ApplicationReturnedForCorrection::class, [self::class, 'handleApplicationReturnedForCorrection']);
        $events->listen(ApplicationDocumentsRequested::class, [self::class, 'handleApplicationDocumentsRequested']);
        $events->listen(ApplicationApproved::class, [self::class, 'handleApplicationApproved']);
        $events->listen(ApplicationRejected::class, [self::class, 'handleApplicationRejected']);
        $events->listen(DeploymentAssignmentCreated::class, [self::class, 'handleDeploymentAssignmentCreated']);
        $events->listen(DeploymentAssignmentCancelled::class, [self::class, 'handleDeploymentAssignmentCancelled']);
    }

    public function handleApplicationSubmitted(ApplicationSubmitted $event): void
    {
        $notification = new ApplicationSubmittedNotification($event->application);

        $this->notifyApplicant($event->application->applicant_id, $notification);
        Notification::send($this->staffReviewers(), clone $notification);
    }

    public function handleApplicationResubmitted(ApplicationResubmitted $event): void
    {
        $notification = new ApplicationResubmittedNotification($event->application);

        $this->notifyApplicant($event->application->applicant_id, $notification);
        Notification::send($this->staffReviewers(), clone $notification);
    }

    public function handleApplicationReturnedForCorrection(ApplicationReturnedForCorrection $event): void
    {
        $this->notifyApplicant(
            $event->application->applicant_id,
            new ApplicationReturnedForCorrectionNotification($event->application, $event->application->decision_reason),
        );
    }

    public function handleApplicationDocumentsRequested(ApplicationDocumentsRequested $event): void
    {
        $this->notifyApplicant(
            $event->application->applicant_id,
            new ApplicationDocumentsRequestedNotification($event->application, $event->application->decision_reason),
        );
    }

    public function handleApplicationApproved(ApplicationApproved $event): void
    {
        $this->notifyApplicant(
            $event->application->applicant_id,
            new ApplicationApprovedNotification($event->application),
        );
    }

    public function handleApplicationRejected(ApplicationRejected $event): void
    {
        $this->notifyApplicant(
            $event->application->applicant_id,
            new ApplicationRejectedNotification($event->application),
        );
    }

    public function handleDeploymentAssignmentCreated(DeploymentAssignmentCreated $event): void
    {
        $assignment = $event->assignment;

        $this->notifyApplicant(
            $assignment->application_id ? $assignment->application()->value('applicant_id') : null,
            new DeploymentAssignmentCreatedNotification($assignment),
        );
    }

    public function handleDeploymentAssignmentCancelled(DeploymentAssignmentCancelled $event): void
    {
        $assignment = $event->assignment;

        $this->notifyApplicant(
            $assignment->application_id ? $assignment->application()->value('applicant_id') : null,
            new DeploymentAssignmentCancelledNotification($assignment),
        );
    }

    /**
     * Staff users responsible for application review. Under the current role
     * model every staff account reviews applications, so the recipient set is
     * simply all active staff users — never students, never all users.
     *
     * @return Collection<int, User>
     */
    private function staffReviewers()
    {
        return User::query()
            ->where('role', User::ROLE_STAFF)
            ->get();
    }

    /**
     * Send to one user without loading them when unnecessary. Notifications
     * are always addressed server-side; client input is never trusted.
     */
    private function notifyApplicant(?int $userId, IlluminateNotification $notification): void
    {
        if ($userId === null) {
            return;
        }

        User::query()->find($userId)?->notify($notification);
    }
}
