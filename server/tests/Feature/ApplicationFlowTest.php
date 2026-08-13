<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\DeploymentAssignment;
use App\Models\HostAgency;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use App\Models\User;
use Database\Factories\ProgramCycleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class ApplicationFlowTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    private function openCycleWithRequirements(): ProgramCycle
    {
        $cycle = ProgramCycle::factory()->open()->create();
        $cycle->requirements()->syncWithPivotValues(
            Requirement::factory()->count(2)->create()->pluck('id'),
            ['is_required' => true],
        );

        return $cycle;
    }

    public function test_a_student_can_create_and_view_their_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.program_cycle.id', $cycle->id);

        $this->assertDatabaseHas('applications', [
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'status' => 'draft',
        ]);

        $this->fromSpa()
            ->getJson('/api/student/applications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_student_cannot_apply_to_a_closed_cycle(): void
    {
        $this->loginAsStudent();
        $cycle = ProgramCycle::factory()->create([
            'application_start' => now()->subMonths(2)->toDateString(),
            'application_deadline' => now()->subMonth()->toDateString(),
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_cycle_id');
    }

    public function test_a_student_cannot_create_duplicate_applications(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();

        Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_cycle_id');
    }

    public function test_a_student_cannot_see_another_students_application(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();

        $other = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$other->id)
            ->assertStatus(403);
    }

    public function test_a_student_can_submit_their_draft_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertNotNull($application->fresh()->submitted_at);
        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'submitted',
        ]);
    }

    public function test_a_student_cannot_submit_another_students_application(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->create([
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/submit')
            ->assertStatus(403);
    }

    public function test_staff_can_review_and_approve_an_application(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'start_review'])
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review');

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $application->refresh();
        $this->assertNotNull($application->approved_at);
        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'status' => 'approved',
        ]);
    }

    public function test_staff_can_request_more_documents(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'start_review',
            ])->assertOk();

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'request_documents',
                'remarks' => 'Please upload a clearer copy of your grades.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'documents_incomplete');
    }

    public function test_reject_requires_a_reason(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remarks');
    }

    public function test_staff_can_reject_an_application(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', [
                'action' => 'reject',
                'remarks' => 'Incomplete requirements.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_an_illegal_transition_is_rejected(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->postJson('/api/staff/applications/'.$application->id.'/review', ['action' => 'approve'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The application cannot move from "draft" via "approve".');
    }

    public function test_staff_can_schedule_and_activate_a_deployment(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->approved()->create(['program_cycle_id' => $cycle->id]);
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployments', [
                'application_id' => $application->id,
                'host_agency_id' => $agency->id,
                'position' => 'Program Aide',
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.host_agency.name', $agency->name);

        $assignment = DeploymentAssignment::where('application_id', $application->id)->firstOrFail();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'for_deployment',
        ]);

        $this->fromSpa()
            ->patchJson('/api/staff/deployments/'.$assignment->id, ['status' => 'active'])
            ->assertOk();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'deployed',
        ]);

        $this->fromSpa()
            ->patchJson('/api/staff/deployments/'.$assignment->id, ['status' => 'completed'])
            ->assertOk();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'completed',
        ]);
    }

    public function test_only_approved_applications_can_be_deployed(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployments', [
                'application_id' => $application->id,
                'host_agency_id' => $agency->id,
                'start_date' => now()->addWeek()->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('deployment_assignments', 0);
    }

    public function test_a_student_can_withdraw_their_submitted_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycleWithRequirements();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications/'.$application->id.'/withdraw')
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');
    }

    public function test_staff_can_filter_the_application_queue(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycleWithRequirements();
        Application::factory()->submitted()->create(['program_cycle_id' => $cycle->id]);
        Application::factory()->approved()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->getJson('/api/staff/applications?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->fromSpa()
            ->getJson('/api/staff/applications')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
