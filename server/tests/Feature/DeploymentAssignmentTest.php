<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\DeploymentAssignment;
use App\Models\DeploymentSite;
use App\Models\DeploymentSlot;
use App\Models\HostAgency;
use App\Models\ProgramCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class DeploymentAssignmentTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createApprovedApplicationWithCycle(): array
    {
        $cycle = ProgramCycle::factory()->create([
            'status' => 'open',
            'application_start' => now()->subMonth(),
            'application_deadline' => now()->addMonth(),
        ]);
        $student = User::factory()->student()->create();
        $application = Application::factory()->approved()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        return ['application' => $application, 'cycle' => $cycle, 'student' => $student];
    }

    private function createSlotForCycle(ProgramCycle $cycle, DeploymentSite $site, int $capacity = 5): DeploymentSlot
    {
        return DeploymentSlot::factory()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
            'capacity' => $capacity,
            'status' => 'active',
        ]);
    }

    private function createActiveSiteWithAgency(): array
    {
        $agency = HostAgency::factory()->create(['is_active' => true]);
        $site = DeploymentSite::factory()->create([
            'host_agency_id' => $agency->id,
            'is_active' => true,
        ]);

        return ['agency' => $agency, 'site' => $site];
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_assign(): void
    {
        $this->fromSpa()
            ->postJson('/api/staff/applications/1/assign', ['deployment_slot_id' => 1])
            ->assertUnauthorized();
    }

    public function test_student_cannot_assign(): void
    {
        $this->loginAsStudent();
        ['application' => $application] = $this->createApprovedApplicationWithCycle();

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", ['deployment_slot_id' => 1])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_list_assignments(): void
    {
        $this->fromSpa()
            ->getJson('/api/staff/deployments')
            ->assertUnauthorized();
    }

    public function test_student_cannot_list_assignments(): void
    {
        $this->loginAsStudent();
        $this->fromSpa()
            ->getJson('/api/staff/deployments')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_get_deployment_options(): void
    {
        $this->fromSpa()
            ->getJson('/api/staff/applications/1/deployment-options')
            ->assertUnauthorized();
    }

    public function test_student_cannot_get_deployment_options(): void
    {
        $this->loginAsStudent();
        ['application' => $application] = $this->createApprovedApplicationWithCycle();

        $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/deployment-options")
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Assignment Creation
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_assign_approved_application_to_slot(): void
    {
        $this->loginAsStaff();
        ['application' => $application] = $this->createApprovedApplicationWithCycle();
        ['agency' => $agency, 'site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($application->programCycle, $site, 5);

        $response = $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.deployment_slot_id', $slot->id)
            ->assertJsonPath('data.position', $slot->title)
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertDatabaseHas('deployment_assignments', [
            'application_id' => $application->id,
            'deployment_slot_id' => $slot->id,
            'host_agency_id' => $agency->id,
            'deployment_site_id' => $site->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_assignment_validates_deployment_slot_exists(): void
    {
        $this->loginAsStaff();
        ['application' => $application] = $this->createApprovedApplicationWithCycle();

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => 9999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deployment_slot_id']);
    }

    public function test_assignment_validates_deployment_slot_id_required(): void
    {
        $this->loginAsStaff();
        ['application' => $application] = $this->createApprovedApplicationWithCycle();

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deployment_slot_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Application Eligibility
    |--------------------------------------------------------------------------
    */

    public function test_pending_application_cannot_be_assigned(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create(['status' => 'open']);
        $student = User::factory()->student()->create();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'status' => 'draft',
        ]);
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409);
    }

    public function test_submitted_application_cannot_be_assigned(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create(['status' => 'open']);
        $student = User::factory()->student()->create();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409);
    }

    public function test_rejected_application_cannot_be_assigned(): void
    {
        $this->loginAsStaff();
        ['application' => $application] = $this->createApprovedApplicationWithCycle();
        $application->update(['status' => 'rejected']);
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($application->programCycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409);
    }

    public function test_withdrawn_application_cannot_be_assigned(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        $application->update(['status' => 'withdrawn']);
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409);
    }

    /*
    |--------------------------------------------------------------------------
    | Program Cycle Consistency
    |--------------------------------------------------------------------------
    */

    public function test_cannot_assign_to_slot_from_different_program_cycle(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle1] = $this->createApprovedApplicationWithCycle();
        $cycle2 = ProgramCycle::factory()->create(['status' => 'open']);
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle2, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'The selected slot does not belong to the same program cycle as the application.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Slot Eligibility
    |--------------------------------------------------------------------------
    */

    public function test_cannot_assign_to_inactive_slot(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = DeploymentSlot::factory()->inactive()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'The selected deployment slot is not active.']);
    }

    public function test_cannot_assign_to_slot_at_inactive_site(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        $agency = HostAgency::factory()->create(['is_active' => true]);
        $site = DeploymentSite::factory()->inactive()->create(['host_agency_id' => $agency->id]);
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'The deployment site for this slot is not active.']);
    }

    public function test_cannot_assign_to_slot_at_inactive_agency(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        $agency = HostAgency::factory()->inactive()->create();
        $site = DeploymentSite::factory()->create(['host_agency_id' => $agency->id]);
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'The host agency for this slot is not active.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Capacity
    |--------------------------------------------------------------------------
    */

    public function test_capacity_1_allows_one_assignment(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 1);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('deployment_assignments', 1);
    }

    public function test_capacity_1_rejects_second_assignment(): void
    {
        $this->loginAsStaff();
        ['cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 1);

        $student1 = User::factory()->student()->create();
        $app1 = Application::factory()->approved()->create([
            'applicant_id' => $student1->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $student2 = User::factory()->student()->create();
        $app2 = Application::factory()->approved()->create([
            'applicant_id' => $student2->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$app1->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$app2->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'The selected deployment slot has no available capacity.']);

        $this->assertDatabaseCount('deployment_assignments', 1);
    }

    public function test_capacity_5_allows_five_assignments(): void
    {
        $this->loginAsStaff();
        ['cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 5);

        for ($i = 0; $i < 5; $i++) {
            $student = User::factory()->student()->create();
            $app = Application::factory()->approved()->create([
                'applicant_id' => $student->id,
                'program_cycle_id' => $cycle->id,
            ]);

            $this->fromSpa()
                ->postJson("/api/staff/applications/{$app->id}/assign", [
                    'deployment_slot_id' => $slot->id,
                ])
                ->assertStatus(201);
        }

        $this->assertDatabaseCount('deployment_assignments', 5);
    }

    public function test_capacity_5_rejects_sixth_assignment(): void
    {
        $this->loginAsStaff();
        ['cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 5);

        for ($i = 0; $i < 5; $i++) {
            $student = User::factory()->student()->create();
            $app = Application::factory()->approved()->create([
                'applicant_id' => $student->id,
                'program_cycle_id' => $cycle->id,
            ]);
            DeploymentAssignment::create([
                'application_id' => $app->id,
                'deployment_slot_id' => $slot->id,
                'host_agency_id' => $site->host_agency_id,
                'deployment_site_id' => $site->id,
                'position' => $slot->title,
                'start_date' => now()->toDateString(),
                'status' => 'scheduled',
                'assigned_by' => User::factory()->staff()->create()->id,
                'assigned_at' => now(),
            ]);
        }

        $student6 = User::factory()->student()->create();
        $app6 = Application::factory()->approved()->create([
            'applicant_id' => $student6->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$app6->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('deployment_assignments', 5);
    }

    public function test_cancelled_assignment_releases_capacity(): void
    {
        $this->loginAsStaff();
        ['cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 1);

        $student1 = User::factory()->student()->create();
        $app1 = Application::factory()->approved()->create([
            'applicant_id' => $student1->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $response = $this->fromSpa()
            ->postJson("/api/staff/applications/{$app1->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $assignmentId = $response->json('data.id');

        $this->fromSpa()
            ->patchJson("/api/staff/deployments/{$assignmentId}/cancel")
            ->assertOk();

        $student2 = User::factory()->student()->create();
        $app2 = Application::factory()->approved()->create([
            'applicant_id' => $student2->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$app2->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('deployment_assignments', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Assignment Prevention
    |--------------------------------------------------------------------------
    */

    public function test_student_cannot_have_two_active_assignments_same_cycle(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['agency' => $agency, 'site' => $site] = $this->createActiveSiteWithAgency();
        $slot1 = $this->createSlotForCycle($cycle, $site, 5);
        $slot2 = DeploymentSlot::factory()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
            'capacity' => 5,
            'status' => 'active',
        ]);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot1->id,
            ])
            ->assertStatus(201);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot2->id,
            ])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'This student already has an active deployment assignment for this program cycle.']);
    }

    public function test_cancelled_assignment_allows_new_assignment_same_cycle(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot1 = $this->createSlotForCycle($cycle, $site, 5);
        $slot2 = DeploymentSlot::factory()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
            'capacity' => 5,
            'status' => 'active',
        ]);

        $response = $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot1->id,
            ])
            ->assertStatus(201);

        $assignmentId = $response->json('data.id');

        $this->fromSpa()
            ->patchJson("/api/staff/deployments/{$assignmentId}/cancel")
            ->assertOk();

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot2->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('deployment_assignments', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Concurrency Test
    |--------------------------------------------------------------------------
    */

    public function test_capacity_enforced_when_two_requests_race_via_service(): void
    {
        $this->loginAsStaff();
        ['cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 1);

        $student1 = User::factory()->student()->create();
        $app1 = Application::factory()->approved()->create([
            'applicant_id' => $student1->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $student2 = User::factory()->student()->create();
        $app2 = Application::factory()->approved()->create([
            'applicant_id' => $student2->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $staff = User::factory()->staff()->create();

        // First assignment succeeds
        $service = app(\App\Services\DeploymentAssignmentService::class);
        $service->assign($app1, $slot, $staff);

        // Second assignment fails because capacity is full
        $caught = false;
        try {
            $service->assign($app2, $slot, $staff);
        } catch (\DomainException) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Expected DomainException for capacity exceeded');
        $this->assertDatabaseCount('deployment_assignments', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel Assignment
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_cancel_scheduled_assignment(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $response = $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $assignmentId = $response->json('data.id');

        $this->fromSpa()
            ->patchJson("/api/staff/deployments/{$assignmentId}/cancel", [
                'remarks' => 'Student requested reassignment',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('deployment_assignments', [
            'id' => $assignmentId,
            'status' => 'cancelled',
        ]);

        // Application should be reverted to approved
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);
    }

    public function test_cannot_cancel_already_cancelled_assignment(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $response = $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $assignmentId = $response->json('data.id');

        $this->fromSpa()
            ->patchJson("/api/staff/deployments/{$assignmentId}/cancel")
            ->assertOk();

        $this->fromSpa()
            ->patchJson("/api/staff/deployments/{$assignmentId}/cancel")
            ->assertStatus(422);
    }

    public function test_student_cannot_cancel_assignment(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $response = $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $assignmentId = $response->json('data.id');

        $this->loginAsStudent();

        $this->fromSpa()
            ->patchJson("/api/staff/deployments/{$assignmentId}/cancel")
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Deployment Options
    |--------------------------------------------------------------------------
    */

    public function test_deployment_options_returns_eligible_slots(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['agency' => $agency, 'site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 5);

        $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/deployment-options")
            ->assertOk()
            ->assertJsonPath('data.program_cycle.id', $cycle->id)
            ->assertJsonCount(1, 'data.host_agencies')
            ->assertJsonPath('data.host_agencies.0.id', $agency->id)
            ->assertJsonCount(1, 'data.host_agencies.0.deployment_sites.0.slots')
            ->assertJsonPath('data.host_agencies.0.deployment_sites.0.slots.0.id', $slot->id)
            ->assertJsonPath('data.host_agencies.0.deployment_sites.0.slots.0.capacity', 5)
            ->assertJsonPath('data.host_agencies.0.deployment_sites.0.slots.0.assigned_count', 0)
            ->assertJsonPath('data.host_agencies.0.deployment_sites.0.slots.0.available_count', 5);
    }

    public function test_deployment_options_excludes_inactive_agencies(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $this->createSlotForCycle($cycle, $site);

        $inactiveAgency = HostAgency::factory()->inactive()->create();
        $inactiveSite = DeploymentSite::factory()->create(['host_agency_id' => $inactiveAgency->id]);
        $this->createSlotForCycle($cycle, $inactiveSite);

        $response = $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/deployment-options")
            ->assertOk();

        $agencyIds = collect($response->json('data.host_agencies'))->pluck('id')->all();
        $this->assertNotContains($inactiveAgency->id, $agencyIds);
    }

    public function test_deployment_options_excludes_inactive_sites(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        $agency = HostAgency::factory()->create(['is_active' => true]);
        $activeSite = DeploymentSite::factory()->create(['host_agency_id' => $agency->id, 'is_active' => true]);
        $inactiveSite = DeploymentSite::factory()->inactive()->create(['host_agency_id' => $agency->id]);
        $this->createSlotForCycle($cycle, $activeSite);
        $this->createSlotForCycle($cycle, $inactiveSite);

        $response = $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/deployment-options")
            ->assertOk();

        $sites = collect($response->json('data.host_agencies.0.deployment_sites'));
        $this->assertCount(1, $sites);
        $this->assertEquals($activeSite->id, $sites->first()['id']);
    }

    public function test_deployment_options_excludes_inactive_slots(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $activeSlot = $this->createSlotForCycle($cycle, $site);
        $inactiveSlot = DeploymentSlot::factory()->inactive()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);

        $response = $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/deployment-options")
            ->assertOk();

        $slots = collect($response->json('data.host_agencies.0.deployment_sites.0.slots'));
        $this->assertCount(1, $slots);
        $this->assertEquals($activeSlot->id, $slots->first()['id']);
    }

    public function test_deployment_options_excludes_full_slots(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $fullSlot = $this->createSlotForCycle($cycle, $site, 1);

        $otherStudent = User::factory()->student()->create();
        $otherApp = Application::factory()->approved()->create([
            'applicant_id' => $otherStudent->id,
            'program_cycle_id' => $cycle->id,
        ]);
        DeploymentAssignment::create([
            'application_id' => $otherApp->id,
            'deployment_slot_id' => $fullSlot->id,
            'host_agency_id' => $site->host_agency_id,
            'deployment_site_id' => $site->id,
            'position' => $fullSlot->title,
            'start_date' => now()->toDateString(),
            'status' => 'scheduled',
            'assigned_by' => User::factory()->staff()->create()->id,
            'assigned_at' => now(),
        ]);

        $response = $this->fromSpa()
            ->getJson("/api/staff/applications/{$application->id}/deployment-options")
            ->assertOk();

        $slots = collect($response->json('data.host_agencies.0.deployment_sites.0.slots'));
        $slotData = $slots->firstWhere('id', $fullSlot->id);
        $this->assertEquals(0, $slotData['available_count']);
    }

    /*
    |--------------------------------------------------------------------------
    | List Assignments
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_list_assignments(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $this->fromSpa()
            ->getJson('/api/staff/deployments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_filters_by_status(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $this->fromSpa()
            ->getJson('/api/staff/deployments?status=scheduled')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->fromSpa()
            ->getJson('/api/staff/deployments?status=completed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_list_searches_by_student_name(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $studentName = $application->applicant->name;

        $this->fromSpa()
            ->getJson('/api/staff/deployments?search=' . urlencode($studentName))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Show Single Assignment
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_view_single_assignment(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $response = $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $assignmentId = $response->json('data.id');

        $this->fromSpa()
            ->getJson("/api/staff/deployments/{$assignmentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $assignmentId)
            ->assertJsonPath('data.deployment_slot_id', $slot->id)
            ->assertJsonPath('data.position', $slot->title);
    }

    public function test_student_can_view_own_assignment(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle, 'student' => $student] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $this->loginAs($student)->assertOk();

        $this->fromSpa()
            ->getJson("/api/student/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.assignment.deployment_slot_id', $slot->id)
            ->assertJsonPath('data.assignment.position', $slot->title);
    }

    public function test_student_cannot_view_other_students_assignment(): void
    {
        $this->loginAsStaff();
        ['application' => $application, 'cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site);

        $this->fromSpa()
            ->postJson("/api/staff/applications/{$application->id}/assign", [
                'deployment_slot_id' => $slot->id,
            ])
            ->assertStatus(201);

        $otherStudent = User::factory()->student()->create();
        $this->loginAs($otherStudent)->assertOk();

        $this->fromSpa()
            ->getJson("/api/student/applications/{$application->id}")
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Capacity Counting
    |--------------------------------------------------------------------------
    */

    public function test_assigned_count_reflects_real_assignments(): void
    {
        $this->loginAsStaff();
        ['cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 10);

        $this->assertEquals(0, $slot->fresh()->assigned_count);
        $this->assertEquals(10, $slot->fresh()->available_count);

        // Create an assignment
        $student = User::factory()->student()->create();
        $app = Application::factory()->approved()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        DeploymentAssignment::create([
            'application_id' => $app->id,
            'deployment_slot_id' => $slot->id,
            'host_agency_id' => $site->host_agency_id,
            'deployment_site_id' => $site->id,
            'position' => $slot->title,
            'start_date' => now()->toDateString(),
            'status' => 'scheduled',
            'assigned_by' => User::factory()->staff()->create()->id,
            'assigned_at' => now(),
        ]);

        $slot->refresh();
        $this->assertEquals(1, $slot->assigned_count);
        $this->assertEquals(9, $slot->available_count);
    }

    public function test_cancelled_assignments_not_counted_in_capacity(): void
    {
        $this->loginAsStaff();
        ['cycle' => $cycle] = $this->createApprovedApplicationWithCycle();
        ['site' => $site] = $this->createActiveSiteWithAgency();
        $slot = $this->createSlotForCycle($cycle, $site, 5);

        $student = User::factory()->student()->create();
        $app = Application::factory()->approved()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        DeploymentAssignment::create([
            'application_id' => $app->id,
            'deployment_slot_id' => $slot->id,
            'host_agency_id' => $site->host_agency_id,
            'deployment_site_id' => $site->id,
            'position' => $slot->title,
            'start_date' => now()->toDateString(),
            'status' => 'cancelled',
            'assigned_by' => User::factory()->staff()->create()->id,
            'assigned_at' => now(),
        ]);

        $slot->refresh();
        $this->assertEquals(0, $slot->assigned_count);
        $this->assertEquals(5, $slot->available_count);
    }
}
