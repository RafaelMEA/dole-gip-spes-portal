<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\DeploymentAssignment;
use App\Models\DeploymentSlot;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeploymentAssignmentService
{
    /**
     * Get eligible deployment options for a specific approved application.
     * Returns only active slots belonging to the same program cycle, from active sites and active agencies,
     * with available capacity.
     */
    public function getDeploymentOptions(Application $application): array
    {
        $this->assertEligibleForAssignment($application);

        $programCycleId = $application->program_cycle_id;

        $hostAgencies = \App\Models\HostAgency::query()
            ->where('is_active', true)
            ->whereHas('deploymentSites', function ($query) use ($programCycleId) {
                $query->where('is_active', true)
                    ->whereHas('deploymentSlots', function ($slotQuery) use ($programCycleId) {
                        $slotQuery->where('program_cycle_id', $programCycleId)
                            ->where('status', 'active');
                    });
            })
            ->with([
                'deploymentSites' => function ($query) use ($programCycleId) {
                    $query->where('is_active', true)
                        ->with([
                            'deploymentSlots' => function ($slotQuery) use ($programCycleId) {
                                $slotQuery->where('program_cycle_id', $programCycleId)
                                    ->where('status', 'active')
                                    ->withCount([
                                        'deploymentAssignments as active_assignments_count' => function ($aQuery) {
                                            $aQuery->whereIn('status', ['scheduled', 'active']);
                                        },
                                    ]);
                            },
                        ])
                        ->whereHas('deploymentSlots', function ($slotQuery) use ($programCycleId) {
                            $slotQuery->where('program_cycle_id', $programCycleId)
                                ->where('status', 'active');
                        });
                },
            ])
            ->get();

        return [
            'program_cycle' => $application->programCycle->only(['id', 'name', 'program_id']),
            'host_agencies' => $hostAgencies->map(function ($agency) {
                return [
                    'id' => $agency->id,
                    'name' => $agency->name,
                    'deployment_sites' => $agency->deploymentSites->map(function ($site) {
                        return [
                            'id' => $site->id,
                            'name' => $site->name,
                            'slots' => $site->deploymentSlots->map(function ($slot) {
                                $assignedCount = $slot->active_assignments_count ?? 0;
                                return [
                                    'id' => $slot->id,
                                    'title' => $slot->title,
                                    'capacity' => $slot->capacity,
                                    'assigned_count' => $assignedCount,
                                    'available_count' => max(0, $slot->capacity - $assignedCount),
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }

    /**
     * Assign an approved student to a deployment slot.
     * Uses a database transaction with row locking to prevent race conditions.
     */
    public function assign(Application $application, DeploymentSlot $slot, User $staff): DeploymentAssignment
    {
        return DB::transaction(function () use ($application, $slot, $staff) {
            // Lock the deployment slot row for update to prevent concurrent capacity issues
            $lockedSlot = DeploymentSlot::query()
                ->where('id', $slot->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Lock the application row for update
            $lockedApplication = Application::query()
                ->where('id', $application->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Verify application is eligible
            $this->assertEligibleForAssignment($lockedApplication);

            // 2. Verify slot is active
            if (! $lockedSlot->isActive()) {
                throw new DomainException('The selected deployment slot is not active.');
            }

            // 3. Verify deployment site is active
            $deploymentSite = $lockedSlot->deploymentSite;
            if (! $deploymentSite || ! $deploymentSite->is_active) {
                throw new DomainException('The deployment site for this slot is not active.');
            }

            // 4. Verify host agency is active
            $hostAgency = $deploymentSite->hostAgency;
            if (! $hostAgency || ! $hostAgency->is_active) {
                throw new DomainException('The host agency for this slot is not active.');
            }

            // 5. Verify slot belongs to the same program cycle as the application
            if ($lockedSlot->program_cycle_id !== $lockedApplication->program_cycle_id) {
                throw new DomainException(
                    'The selected slot does not belong to the same program cycle as the application.',
                );
            }

            // 6. Verify student has no active assignment for this program cycle
            $hasActiveAssignment = DeploymentAssignment::query()
                ->whereHas('application', function ($query) use ($lockedApplication) {
                    $query->where('program_cycle_id', $lockedApplication->program_cycle_id);
                })
                ->where('application_id', $lockedApplication->id)
                ->whereIn('status', ['scheduled', 'active'])
                ->exists();

            if ($hasActiveAssignment) {
                throw new DomainException(
                    'This student already has an active deployment assignment for this program cycle.',
                );
            }

            // 7. Remove any previous cancelled assignment for this application (unique constraint on application_id)
            DeploymentAssignment::where('application_id', $lockedApplication->id)
                ->where('status', 'cancelled')
                ->delete();

            // 7. Verify slot has available capacity
            $currentAssignments = DeploymentAssignment::query()
                ->where('deployment_slot_id', $lockedSlot->id)
                ->whereIn('status', ['scheduled', 'active'])
                ->count();

            if ($currentAssignments >= $lockedSlot->capacity) {
                throw new DomainException('The selected deployment slot has no available capacity.');
            }

            // 8. Create the assignment
            $assignment = DeploymentAssignment::create([
                'application_id' => $lockedApplication->id,
                'deployment_slot_id' => $lockedSlot->id,
                'host_agency_id' => $hostAgency->id,
                'deployment_site_id' => $deploymentSite->id,
                'position' => $lockedSlot->title,
                'start_date' => now()->toDateString(),
                'status' => 'scheduled',
                'assigned_by' => $staff->id,
                'assigned_at' => now(),
            ]);

            AuditLogger::log('assignment.created', $assignment, null, $assignment->only([
                'application_id', 'deployment_slot_id', 'host_agency_id',
                'deployment_site_id', 'position', 'start_date', 'status',
            ]), actor: $staff);

            // 9. Update application status to for_deployment
            try {
                $appService = app(ApplicationService::class);
                $appService->scheduleForDeployment($lockedApplication, $staff);
            } catch (DomainException) {
                // Application may already be in a compatible state; assignment still stands.
            }

            return $assignment->load([
                'deploymentSlot.programCycle',
                'deploymentSlot.deploymentSite',
                'hostAgency',
                'deploymentSite',
                'application.applicant',
                'assignedBy',
            ]);
        });
    }

    /**
     * Cancel an active deployment assignment.
     * Returns capacity to the slot and reverts application to approved.
     */
    public function cancel(DeploymentAssignment $assignment, User $staff, ?string $remarks = null): DeploymentAssignment
    {
        return DB::transaction(function () use ($assignment, $staff, $remarks) {
            // Lock the assignment row
            $lockedAssignment = DeploymentAssignment::query()
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedAssignment->status, [\App\Enums\DeploymentAssignmentStatus::Completed, \App\Enums\DeploymentAssignmentStatus::Cancelled])) {
                throw new DomainException('A completed or cancelled assignment cannot be cancelled.');
            }

            $previousStatus = $lockedAssignment->status->value;

            $lockedAssignment->update([
                'status' => 'cancelled',
                'remarks' => $remarks ?? $lockedAssignment->remarks,
            ]);

            AuditLogger::log('assignment.cancelled', $lockedAssignment, [
                'status' => $previousStatus,
            ], [
                'status' => 'cancelled',
            ], reason: $remarks, actor: $staff);

            // Revert application status to approved
            $application = $lockedAssignment->application;
            if (in_array($application->status->value, ['for_deployment', 'deployed'])) {
                $application->update(['status' => ApplicationStatus::Approved]);
                $application->logStatusChange($staff->id, 'Deployment assignment cancelled.', 'assignment_cancelled');
            }

            return $lockedAssignment->load([
                'deploymentSlot.programCycle',
                'deploymentSlot.deploymentSite',
                'hostAgency',
                'deploymentSite',
                'application.applicant',
                'assignedBy',
            ]);
        });
    }

    /**
     * Assert that an application is eligible for a deployment assignment.
     */
    private function assertEligibleForAssignment(Application $application): void
    {
        $eligibleStatuses = [
            ApplicationStatus::Approved->value,
            ApplicationStatus::ForDeployment->value,
        ];

        if (! in_array($application->status->value, $eligibleStatuses, true)) {
            throw new DomainException(
                'Only approved applications can be assigned for deployment. Current status: '
                . $application->status->value,
            );
        }

        // Verify program cycle exists and is not closed/archived
        $cycle = $application->programCycle;
        if (! $cycle) {
            throw new DomainException('Application has no associated program cycle.');
        }

        $cycleStatus = $cycle->storedStatus()->value;
        if (in_array($cycleStatus, ['draft', 'archived'], true)) {
            throw new DomainException('The program cycle for this application is not active.');
        }
    }
}
