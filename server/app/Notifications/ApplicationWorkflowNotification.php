<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Base class for application-workflow notifications delivered through the
 * database channel.
 *
 * The stored payload is intentionally small: a title, a short message, a
 * machine-readable type, an in-app action URL and the identifiers the
 * frontend needs to navigate. No document contents, tokens or profile data
 * are ever copied into the payload.
 *
 * Adding a future channel (mail, broadcast) means appending it to via() or
 * attaching another queued listener to the domain event — business logic
 * stays untouched.
 */
abstract class ApplicationWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(protected readonly Application $application) {}

    /**
     * Only in-app delivery is implemented for now. Mail/SMS/broadcast will be
     * appended here (or via additional queued listeners on the domain event).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The database-channel payload. Shaped for the frontend notification
     * centre; differs per audience so students and staff get appropriate
     * wording and links from one authoritative class.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $audience = $notifiable instanceof User && $notifiable->isStaff()
            ? 'staff'
            : 'student';

        [$title, $message] = $this->titleAndMessage($audience);

        return [
            'type' => $this->type(),
            'title' => $title,
            'message' => $message,
            'action_url' => $audience === 'staff'
                ? '/staff/applications/'.$this->application->getKey()
                : '/student/applications/'.$this->application->getKey(),
            'application_id' => $this->application->getKey(),
        ];
    }

    /**
     * Short program label for messages, e.g. "GIP" / "SPES"; falls back to
     * the full program name for anything unrecognised.
     */
    protected function programLabel(): string
    {
        // loadMissing caches on the shared application instance, so a single
        // broadcast to many staff recipients resolves this exactly once.
        $cycle = $this->application->relationLoaded('programCycle')
            ? $this->application->programCycle
            : $this->application->programCycle()->with('program')->first();

        if ($cycle === null) {
            return 'program';
        }

        $program = $cycle->program ?? $cycle->program()->first();

        if ($program === null) {
            return 'program';
        }

        $label = trim(strtoupper((string) $program->slug));

        return $label !== '' ? $label : $program->name;
    }

    /**
     * @return array{0: string, 1: string}
     */
    abstract protected function type(): string;

    /**
     * @return array{0: string, 1: string} [title, message]
     */
    abstract protected function titleAndMessage(string $audience): array;
}
