<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DeploymentAssignment;
use App\Models\DeploymentSite;
use App\Models\DeploymentSlot;
use App\Models\ApplicationDocument;
use App\Models\HostAgency;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Records immutable audit entries for meaningful business events.
 *
 * The actor is always the authenticated user (or an explicitly passed user in
 * service-layer flows); it is never taken from client input. Only whitelisted
 * business fields are captured in old/new values, and a final scrub removes
 * any secret-like keys as defence in depth. File contents and storage paths
 * are never logged.
 *
 * Logging participates in the caller's database transaction: if the surrounding
 * state change fails or rolls back, the audit entry is rolled back with it, so
 * the log can never claim an event that did not happen.
 */
class AuditLogger
{
    /**
     * Keys that must never appear in logged values, even defensively.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'file_path',
        'file_contents',
    ];

    /**
     * The business fields worth recording per auditable entity. Anything not
     * listed is dropped before it can reach the database.
     *
     * @var array<class-string, array<int, string>>
     */
    private const TRACKED_FIELDS = [
        ApplicationDocument::class => [
            'application_id', 'requirement_id', 'file_name', 'mime_type',
            'file_size', 'verification_status', 'rejection_reason',
            'verified_by', 'verified_at', 'uploaded_at',
        ],
        DeploymentSlot::class => [
            'program_cycle_id', 'deployment_site_id', 'title', 'description',
            'capacity', 'status',
        ],
        DeploymentAssignment::class => [
            'application_id', 'deployment_slot_id', 'host_agency_id',
            'deployment_site_id', 'position', 'start_date', 'end_date', 'status',
        ],
        HostAgency::class => [
            'name', 'agency_type', 'address', 'contact_person',
            'contact_number', 'email', 'is_active',
        ],
        DeploymentSite::class => [
            'host_agency_id', 'name', 'address', 'city', 'region',
            'contact_person', 'contact_number', 'email', 'description', 'is_active',
        ],
    ];

    /**
     * Record an audit entry using the container-resolved logger.
     *
     * Resolving through the container keeps call sites terse while allowing
     * tests to swap this service for a spy or a failing double.
     */
    public static function log(
        string $action,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?string $reason = null,
        ?User $actor = null,
    ): AuditLog {
        return app(self::class)->record(
            $action, $auditable, $oldValues, $newValues, $metadata, $reason, $actor,
        );
    }

    /**
     * Persist one audit entry for the given model.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        string $action,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?string $reason = null,
        ?User $actor = null,
    ): AuditLog {
        if ($action === '' || ! str_contains($action, '.')) {
            throw new InvalidArgumentException("Audit action \"$action\" must use the \"entity.event\" form.");
        }

        /** @var User|null $resolvedActor */
        $resolvedActor = $actor ?? Auth::user();

        $entry = (new AuditLog)->forceFill([
            'user_id' => $resolvedActor?->id,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $this->scrub($this->filter($auditable, $oldValues)),
            'new_values' => $this->scrub($this->filter($auditable, $newValues)),
            'metadata' => $this->scrub($metadata),
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_at' => now(),
        ]);
        $entry->save();

        return $entry;
    }

    /**
     * Keep only the tracked business fields for the entity being audited.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function filter(Model $auditable, ?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $tracked = self::TRACKED_FIELDS[$auditable::class]
            ?? self::TRACKED_FIELDS[$auditable->getMorphClass()]
            ?? null;

        if ($tracked === null) {
            throw new InvalidArgumentException(
                'No tracked fields are configured for '.$auditable::class.'. Add them to '.self::class.'.',
            );
        }

        return collect($values)
            ->only($tracked)
            ->all();
    }

    /**
     * Remove secret-like keys as defence in depth; nothing sensitive should
     * reach this point because only whitelisted fields are captured.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function scrub(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return collect($values)
            ->reject(fn ($value, $key) => in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true))
            ->map(fn ($value) => is_array($value) ? $this->scrub($value) : $value)
            ->all();
    }
}
