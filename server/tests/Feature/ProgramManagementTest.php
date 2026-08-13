<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    private function cyclePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'GIP 2026 Batch',
            'description' => 'Batch description.',
            'total_slots' => 20,
            'application_start' => now()->addDays(2)->toDateString(),
            'application_deadline' => now()->addWeeks(3)->toDateString(),
            'deployment_start' => now()->addWeeks(4)->toDateString(),
            'deployment_end' => now()->addWeeks(8)->toDateString(),
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | Staff: programs
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_list_programs(): void
    {
        $this->loginAsStaff();
        Program::factory()->gip()->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/programs')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_can_view_program_details(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();
        $program->programCycles()->create($this->cyclePayload());

        $this->fromSpa()
            ->getJson("/api/staff/catalog/programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $program->id)
            ->assertJsonCount(1, 'data.cycles');
    }

    public function test_staff_can_create_program(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/programs', [
                'name' => 'Government Internship Program',
                'slug' => 'gip',
                'description' => 'Description',
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Government Internship Program');

        $this->assertDatabaseHas('programs', ['slug' => 'gip']);
    }

    public function test_staff_can_update_program(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/programs/{$program->id}", [
                'name' => 'Renamed Program',
                'slug' => 'renamed',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Program')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_staff_can_delete_program_without_cycles(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/programs/{$program->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    public function test_staff_cannot_delete_program_that_still_has_cycles(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();
        $program->programCycles()->create($this->cyclePayload());

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/programs/{$program->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('programs', ['id' => $program->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Staff: cycles
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_create_cycle(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload([
                'program_id' => $program->id,
                'status' => 'draft',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'GIP 2026 Batch')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slots_remaining', 20);

        $this->assertDatabaseHas('program_cycles', [
            'name' => 'GIP 2026 Batch',
            'program_id' => $program->id,
            'status' => 'draft',
        ]);
    }

    public function test_staff_can_view_cycle_details(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();
        $cycle = $program->programCycles()->create($this->cyclePayload());

        $this->fromSpa()
            ->getJson("/api/staff/catalog/cycles/{$cycle->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.program.id', $program->id)
            ->assertJsonPath('data.deployment_start', now()->addWeeks(4)->toDateString());
    }

    public function test_staff_can_update_cycle(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();
        $cycle = $program->programCycles()->create($this->cyclePayload());
        $requirement = Requirement::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/cycles/{$cycle->id}", $this->cyclePayload([
                'program_id' => $program->id,
                'name' => 'Renamed Cycle',
                'total_slots' => 12,
                'requirements' => [$requirement->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Cycle')
            ->assertJsonPath('data.total_slots', 12)
            ->assertJsonPath('data.requirements.0.id', $requirement->id);
    }

    public function test_cycle_must_reference_an_existing_program(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload([
                'program_id' => 9999,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_id');
    }

    public function test_staff_can_publish_a_draft_cycle(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();
        $cycle = $program->programCycles()->create($this->cyclePayload(['status' => 'draft']));

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'upcoming')
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('program_cycles', ['id' => $cycle->id, 'status' => 'upcoming']);
    }

    public function test_staff_can_unpublish_a_cycle(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();
        $cycle = $program->programCycles()->create($this->cyclePayload(['status' => 'open']));

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('program_cycles', ['id' => $cycle->id, 'status' => 'draft']);
    }

    public function test_staff_can_delete_a_cycle_without_applications(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();
        $cycle = $program->programCycles()->create($this->cyclePayload());

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/cycles/{$cycle->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('program_cycles', ['id' => $cycle->id]);
    }

    public function test_staff_cannot_delete_a_cycle_with_applications(): void
    {
        $this->loginAsStaff();
        $student = \App\Models\User::factory()->student()->create();
        $program = Program::factory()->gip()->create();
        $cycle = $program->programCycles()->create($this->cyclePayload(['status' => 'open']));
        Application::factory()->create(['applicant_id' => $student->id, 'program_cycle_id' => $cycle->id]);

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/cycles/{$cycle->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('program_cycles', ['id' => $cycle->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Staff: validation
    |--------------------------------------------------------------------------
    */

    public function test_application_deadline_cannot_precede_start(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload([
                'program_id' => $program->id,
                'application_start' => now()->addWeeks(2)->toDateString(),
                'application_deadline' => now()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('application_deadline');
    }

    public function test_deployment_cannot_start_before_application_deadline(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload([
                'program_id' => $program->id,
                'application_deadline' => now()->addWeeks(6)->toDateString(),
                'deployment_start' => now()->addWeeks(2)->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('deployment_start');
    }

    public function test_deployment_end_cannot_precede_deployment_start(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload([
                'program_id' => $program->id,
                'deployment_start' => now()->addWeeks(4)->toDateString(),
                'deployment_end' => now()->addWeeks(3)->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('deployment_end');
    }

    public function test_invalid_slot_counts_are_rejected(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload([
                'program_id' => $program->id,
                'total_slots' => 0,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('total_slots');

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload([
                'program_id' => $program->id,
                'total_slots' => -5,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('total_slots');
    }

    public function test_invalid_cycle_status_is_rejected(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload([
                'program_id' => $program->id,
                'status' => 'obliterated',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /*
    |--------------------------------------------------------------------------
    | Students: browsing
    |--------------------------------------------------------------------------
    */

    public function test_student_can_view_available_programs(): void
    {
        $this->loginAsStudent();
        Program::factory()->gip()->create();

        $this->fromSpa()
            ->getJson('/api/programs')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_draft_cycles_are_hidden_from_students(): void
    {
        $this->loginAsStudent();
        $program = Program::factory()->gip()->create();
        $program->programCycles()->create($this->cyclePayload(['status' => 'draft']));

        $response = $this->fromSpa()
            ->getJson('/api/programs')
            ->assertOk();

        $this->assertEmpty($response->json('data.0.cycles'));

        $this->fromSpa()
            ->getJson('/api/program-cycles')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_published_cycles_are_visible_to_students(): void
    {
        $this->loginAsStudent();
        $program = Program::factory()->gip()->create();
        $program->programCycles()->create($this->cyclePayload(['status' => 'upcoming']));

        $this->fromSpa()
            ->getJson('/api/program-cycles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'upcoming');
    }

    public function test_student_cannot_view_a_draft_cycle_directly(): void
    {
        $this->loginAsStudent();
        $program = Program::factory()->gip()->create();
        $cycle = $program->programCycles()->create($this->cyclePayload(['status' => 'draft']));

        $this->fromSpa()
            ->getJson("/api/program-cycles/{$cycle->id}")
            ->assertNotFound();
    }

    public function test_inactive_program_is_hidden_from_students(): void
    {
        $this->loginAsStudent();
        Program::factory()->gip()->create(['is_active' => false]);

        $this->fromSpa()
            ->getJson('/api/programs')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cycle_exposes_deployment_dates_and_slot_availability(): void
    {
        $this->loginAsStudent();
        $program = Program::factory()->gip()->create();
        $program->programCycles()->create($this->cyclePayload(['status' => 'upcoming']));

        $this->fromSpa()
            ->getJson('/api/program-cycles')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'deployment_start',
                    'deployment_end',
                    'slots_remaining',
                    'total_slots',
                    'is_accepting_applications',
                    'is_published',
                ]],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Students: authorization
    |--------------------------------------------------------------------------
    */

    public function test_student_cannot_create_program(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/programs', ['name' => 'X', 'slug' => 'x'])
            ->assertStatus(403);
    }

    public function test_student_cannot_update_program(): void
    {
        $this->loginAsStudent();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/programs/{$program->id}", ['name' => 'X', 'slug' => 'x'])
            ->assertStatus(403);
    }

    public function test_student_cannot_delete_program(): void
    {
        $this->loginAsStudent();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/programs/{$program->id}")
            ->assertStatus(403);
    }

    public function test_student_cannot_modify_cycles_or_slots(): void
    {
        $this->loginAsStudent();
        $program = Program::factory()->gip()->create();
        $cycle = $program->programCycles()->create($this->cyclePayload());

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload(['program_id' => $program->id]))
            ->assertStatus(403);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/cycles/{$cycle->id}", $this->cyclePayload([
                'program_id' => $program->id,
                'total_slots' => 999,
            ]))
            ->assertStatus(403);

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/cycles/{$cycle->id}")
            ->assertStatus(403);

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/publish")
            ->assertStatus(403);

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/unpublish")
            ->assertStatus(403);

        $this->assertDatabaseHas('program_cycles', ['id' => $cycle->id, 'total_slots' => 20]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_users_cannot_access_management_apis(): void
    {
        $this->fromSpa()
            ->getJson('/api/staff/catalog/programs')
            ->assertStatus(401);

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', $this->cyclePayload())
            ->assertStatus(401);

        $this->fromSpa()
            ->getJson('/api/programs')
            ->assertStatus(401);
    }
}
