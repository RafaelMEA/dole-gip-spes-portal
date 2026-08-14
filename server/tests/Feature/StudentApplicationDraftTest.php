<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use App\Models\ProgramCycle;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class StudentApplicationDraftTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    private function openCycle(): ProgramCycle
    {
        return ProgramCycle::factory()->open()->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Application creation
    |--------------------------------------------------------------------------
    */

    public function test_an_authenticated_student_can_create_an_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycle();

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.program_cycle.id', $cycle->id);

        $this->assertDatabaseHas('applications', [
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'status' => 'draft',
            'submitted_at' => null,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function test_the_student_identity_is_derived_from_the_authenticated_user(): void
    {
        $student = $this->loginAsStudent();
        $other = User::factory()->student()->create();
        $cycle = $this->openCycle();

        $this->fromSpa()
            ->postJson('/api/student/applications', [
                'program_cycle_id' => $cycle->id,
                'applicant_id' => $other->id,
                'user_id' => $other->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('applications', [
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);
        $this->assertDatabaseMissing('applications', ['applicant_id' => $other->id]);
    }

    public function test_duplicate_applications_for_the_same_cycle_are_rejected(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycle();

        Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_cycle_id');
    }

    public function test_the_database_constraint_rejects_duplicate_applications(): void
    {
        $student = User::factory()->student()->create();
        $cycle = $this->openCycle();

        Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->expectException(QueryException::class);

        Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);
    }

    public function test_a_student_cannot_create_an_application_when_the_cycle_is_closed(): void
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

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_a_student_cannot_create_an_application_when_the_cycle_is_upcoming(): void
    {
        $this->loginAsStudent();
        $cycle = ProgramCycle::factory()->create([
            'application_start' => now()->addWeek()->toDateString(),
            'application_deadline' => now()->addWeeks(2)->toDateString(),
            'status' => 'upcoming',
        ]);

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_cycle_id');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_a_student_cannot_create_an_application_for_an_unpublished_cycle(): void
    {
        $this->loginAsStudent();
        $cycle = ProgramCycle::factory()->draft()->create();

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_cycle_id');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_a_staff_member_cannot_create_an_application(): void
    {
        $this->loginAsStaff();
        $cycle = $this->openCycle();

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(403);

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_a_guest_cannot_create_an_application(): void
    {
        $cycle = $this->openCycle();

        $this->fromSpa()
            ->postJson('/api/student/applications', ['program_cycle_id' => $cycle->id])
            ->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Viewing / listing
    |--------------------------------------------------------------------------
    */

    public function test_a_student_can_view_their_own_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycle();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'remarks' => 'Available during weekdays.',
        ]);

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.remarks', 'Available during weekdays.');
    }

    public function test_a_student_cannot_view_another_students_application(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycle();
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id)
            ->assertStatus(403);
    }

    public function test_a_guest_cannot_view_an_application(): void
    {
        $cycle = $this->openCycle();
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->getJson('/api/student/applications/'.$application->id)
            ->assertStatus(401);
    }

    public function test_listing_only_returns_the_authenticated_students_applications(): void
    {
        $student = $this->loginAsStudent();
        $program = Program::factory()->gip()->create();
        $cycleA = ProgramCycle::factory()->for($program)->create();
        $cycleB = ProgramCycle::factory()->for($program)->create();

        Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycleA->id,
        ]);
        Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycleB->id,
        ]);
        Application::factory()->create([
            'program_cycle_id' => ProgramCycle::factory()->for($program)->create()->id,
        ]);

        $this->fromSpa()
            ->getJson('/api/student/applications')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Draft editing
    |--------------------------------------------------------------------------
    */

    public function test_a_student_can_update_their_own_draft_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycle();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'remarks' => 'Old notes',
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, [
                'remarks' => 'Available during weekdays only.',
            ])
            ->assertOk()
            ->assertJsonPath('data.remarks', 'Available during weekdays only.')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'remarks' => 'Available during weekdays only.',
            'status' => 'draft',
        ]);
    }

    public function test_a_student_can_clear_the_remarks_of_their_draft(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycle();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'remarks' => 'Something to remove',
        ]);

        $this->fromSpa()
            ->patchJson('/api/student/applications/'.$application->id, ['remarks' => ''])
            ->assertOk()
            ->assertJsonPath('data.remarks', null);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'remarks' => null,
        ]);
    }

    public function test_a_student_cannot_update_another_students_draft_application(): void
    {
        $this->loginAsStudent();
        $cycle = $this->openCycle();
        $application = Application::factory()->create([
            'program_cycle_id' => $cycle->id,
            'remarks' => 'Owned by someone else',
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, ['remarks' => 'Hacked'])
            ->assertStatus(403);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'remarks' => 'Owned by someone else',
        ]);
    }

    public function test_a_submitted_application_cannot_be_edited(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycle();
        $application = Application::factory()->submitted()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'remarks' => 'Original remarks',
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, ['remarks' => 'Changed later'])
            ->assertStatus(403);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'remarks' => 'Original remarks',
            'status' => 'submitted',
        ]);
    }

    public function test_a_student_cannot_change_the_status_of_their_application(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycle();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, [
                'remarks' => 'Updated notes',
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'draft',
        ]);
    }

    public function test_a_student_cannot_change_staff_and_system_controlled_fields(): void
    {
        $student = $this->loginAsStudent();
        $other = User::factory()->student()->create();
        $program = Program::factory()->spes()->create();
        $cycle = ProgramCycle::factory()->for($program)->create();
        $otherCycle = ProgramCycle::factory()->for($program)->create();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
            'remarks' => 'Safe',
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, [
                'remarks' => 'Updated',
                'applicant_id' => $other->id,
                'program_cycle_id' => $otherCycle->id,
                'submitted_at' => now()->toISOString(),
                'approved_at' => now()->toISOString(),
                'approved_by' => $other->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.remarks', 'Updated');

        $application->refresh();

        $this->assertSame($student->id, $application->applicant_id);
        $this->assertSame($cycle->id, $application->program_cycle_id);
        $this->assertNull($application->submitted_at);
        $this->assertNull($application->approved_at);
        $this->assertNull($application->approved_by);
    }

    public function test_remarks_longer_than_the_limit_are_rejected(): void
    {
        $student = $this->loginAsStudent();
        $cycle = $this->openCycle();
        $application = Application::factory()->create([
            'applicant_id' => $student->id,
            'program_cycle_id' => $cycle->id,
        ]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, [
                'remarks' => str_repeat('a', 5001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remarks');
    }

    public function test_a_guest_cannot_update_an_application(): void
    {
        $cycle = $this->openCycle();
        $application = Application::factory()->create(['program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->putJson('/api/student/applications/'.$application->id, ['remarks' => 'Hacked'])
            ->assertStatus(401);
    }
}
