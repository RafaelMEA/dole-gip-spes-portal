<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusHistory;
use App\Models\AuditLog;
use App\Models\DeploymentAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Builds the chronological history of an application.
 *
 * Application status transitions live in application_status_history (written
 * transactionally by the workflow service); every other meaningful event
 * (document uploads/replacements/decisions, deployment assignments) lives in
 * the polymorphic audit_logs table. This service merges both sources into a
 * single, newest-first timeline so consumers render one coherent history.
 */
class ApplicationHistoryService
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /**
     * The merged staff timeline for one application: status transitions plus
     * audit events attached to the application's documents and assignment.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function staffTimeline(Application $application, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $events = $this->statusEvents($application)
            ->concat($this->auditEvents($application))
            ->sortByDesc(fn (array $event) => [$event['occurred_at'], $event['sort_key']])
            ->values();

        return $this->paginate($events, $page, $perPage);
    }

    /**
     * The student-facing timeline: status transitions only. Internal audit
     * details (old/new values, metadata) are intentionally excluded; students
     * still see each transition's outcome and any reason recorded for them.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function studentTimeline(Application $application, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $events = $this->statusEvents($application)->map(function (array $event) {
            unset($event['old_values'], $event['new_values'], $event['metadata']);

            return $event;
        })->values();

        return $this->paginate($events, $page, $perPage);
    }

    /**
     * Status transitions as timeline events.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function statusEvents(Application $application): Collection
    {
        return $application->statusHistory()
            ->with(['changedBy:id,name'])
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApplicationStatusHistory $entry): array => [
                'id' => $entry->id,
                'source' => 'status_history',
                'action' => $entry->action,
                'label' => AuditActionLabels::status($entry->action, $entry->status->label()),
                'actor' => $entry->changedBy?->name,
                'occurred_at' => $entry->changed_at?->toISOString(),
                'reason' => $entry->remarks,
                'old_values' => null,
                'new_values' => null,
                'metadata' => null,
                // Secondary sort key keeps same-millisecond rows stable.
                'sort_key' => 's'.$entry->id,
            ]);
    }

    /**
     * Audit events attached to this application's documents and assignment.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function auditEvents(Application $application): Collection
    {
        $documentIds = $application->documents()->pluck('id');
        $assignmentId = $application->deploymentAssignment()->value('id');

        if ($documentIds->isEmpty() && $assignmentId === null) {
            return collect();
        }

        $logs = AuditLog::query()
            ->with(['user:id,name'])
            ->where(function (Builder $query) use ($application, $documentIds, $assignmentId): void {
                $query->where(function (Builder $q) use ($application): void {
                    $q->where('auditable_type', Application::class)
                        ->where('auditable_id', $application->id);
                });

                if ($documentIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $q) use ($documentIds): void {
                        $q->where('auditable_type', ApplicationDocument::class)
                            ->whereIn('auditable_id', $documentIds);
                    });
                }

                if ($assignmentId !== null) {
                    $query->orWhere(function (Builder $q) use ($assignmentId): void {
                        $q->where('auditable_type', DeploymentAssignment::class)
                            ->where('auditable_id', $assignmentId);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'source' => 'audit_log',
                'action' => $log->action,
                'label' => AuditActionLabels::audit($log->action),
                'actor' => $log->user?->name,
                'occurred_at' => $log->created_at?->toISOString(),
                'reason' => $log->reason,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'metadata' => $log->metadata,
                'sort_key' => 'a'.$log->id,
            ]);

        return $logs;
    }

    /**
     * Paginate an already-merged event collection without re-querying.
     *
     * @param  Collection<int, array<string, mixed>>  $events
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginate(Collection $events, int $page, int $perPage): LengthAwarePaginator
    {
        $perPage = min(max(1, $perPage), self::MAX_PER_PAGE);
        $page = max(1, $page);

        return new LengthAwarePaginator(
            $events->forPage($page, $perPage)->values(),
            $events->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }
}
