<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Program;
use App\Models\ProgramCycle;
use App\Models\Requirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class RequirementsManagementTest extends TestCase
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

    private function cycle(array $overrides = [], ?Program $program = null): ProgramCycle
    {
        $program ??= Program::factory()->gip()->create();

        return $program->programCycles()->create($this->cyclePayload($overrides));
    }

    private function requirementPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Certificate of Registration',
            'description' => 'Current semester COR.',
            'slug' => 'certificate-of-registration',
            'is_required' => true,
            'allowed_file_types' => ['pdf', 'jpg', 'png'],
            'max_file_size' => 5120,
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | Staff: cycle requirements
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_list_cycle_requirements(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->getJson("/api/staff/catalog/cycles/{$cycle->id}/requirements")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $requirement->id)
            ->assertJsonPath('data.0.is_required', true);
    }

    public function test_staff_can_create_a_requirement_for_a_cycle(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/requirements", $this->requirementPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Certificate of Registration')
            ->assertJsonPath('data.is_required', true)
            ->assertJsonPath('data.allowed_file_types', ['pdf', 'jpg', 'png'])
            ->assertJsonPath('data.max_file_size', 5120);

        $this->assertDatabaseHas('requirements', ['slug' => 'certificate-of-registration']);
        $this->assertDatabaseHas('program_cycle_requirements', [
            'program_cycle_id' => $cycle->id,
            'is_required' => true,
        ]);
    }

    public function test_staff_can_attach_an_existing_requirement_to_a_cycle(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();
        $requirement = Requirement::factory()->create(['slug' => 'valid-government-id']);

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/requirements", [
                'requirement_id' => $requirement->id,
                'is_required' => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.id', $requirement->id)
            ->assertJsonPath('data.is_required', false);

        $this->assertDatabaseHas('program_cycle_requirements', [
            'program_cycle_id' => $cycle->id,
            'requirement_id' => $requirement->id,
            'is_required' => false,
        ]);
    }

    public function test_staff_cannot_attach_the_same_requirement_twice(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/requirements", [
                'requirement_id' => $requirement->id,
            ])
            ->assertStatus(422);
    }

    public function test_duplicate_requirement_name_within_a_cycle_is_rejected(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();
        $existing = Requirement::factory()->create(['name' => 'Valid ID', 'slug' => 'valid-id']);
        $cycle->requirements()->attach($existing->id, ['is_required' => true]);

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/requirements", [
                'name' => 'valid id',
                'slug' => 'valid-id-2',
                'description' => null,
                'is_required' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_staff_can_update_a_cycle_requirement(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();
        $requirement = Requirement::factory()->create(['slug' => 'certificate-of-registration']);
        $cycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/cycles/{$cycle->id}/requirements/{$requirement->id}", [
                'name' => 'Certificate of Registration (Updated)',
                'slug' => 'certificate-of-registration-updated',
                'description' => null,
                'is_required' => false,
                'max_file_size' => 2048,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Certificate of Registration (Updated)')
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.max_file_size', 2048);

        $this->assertDatabaseHas('program_cycle_requirements', [
            'program_cycle_id' => $cycle->id,
            'requirement_id' => $requirement->id,
            'is_required' => false,
        ]);
    }

    public function test_staff_cannot_update_a_requirement_not_attached_to_the_cycle(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();
        $otherCycle = $this->cycle(['name' => 'SPES 2026 Batch'], $cycle->program);
        $requirement = Requirement::factory()->create();
        $otherCycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/cycles/{$cycle->id}/requirements/{$requirement->id}", [
                'name' => 'Hijacked',
                'slug' => 'hijacked',
            ])
            ->assertStatus(404);
    }

    public function test_staff_can_remove_a_requirement_from_a_cycle(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/cycles/{$cycle->id}/requirements/{$requirement->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('program_cycle_requirements', [
            'program_cycle_id' => $cycle->id,
            'requirement_id' => $requirement->id,
        ]);
    }

    public function test_staff_cannot_remove_a_requirement_from_an_unrelated_cycle(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();
        $otherCycle = $this->cycle(['name' => 'SPES 2026 Batch'], $cycle->program);
        $requirement = Requirement::factory()->create();
        $otherCycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/cycles/{$cycle->id}/requirements/{$requirement->id}")
            ->assertStatus(404);
    }

    public function test_requirement_validation_rejects_invalid_values(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/requirements", [
                'name' => '',
                'slug' => '',
                'allowed_file_types' => ['pdf', '../../etc/passwd'],
                'max_file_size' => -5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug', 'allowed_file_types.1', 'max_file_size']);
    }

    /*
    |--------------------------------------------------------------------------
    | Staff: requirement catalog
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_view_a_single_requirement(): void
    {
        $this->loginAsStaff();
        $requirement = Requirement::factory()->create(['slug' => 'barangay-clearance']);

        $this->fromSpa()
            ->getJson("/api/staff/catalog/requirements/{$requirement->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $requirement->id);
    }

    public function test_staff_can_update_a_catalog_requirement(): void
    {
        $this->loginAsStaff();
        $requirement = Requirement::factory()->create(['slug' => 'barangay-clearance']);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/requirements/{$requirement->id}", [
                'name' => 'Clearance',
                'slug' => 'barangay-clearance',
                'description' => null,
                'is_active' => true,
                'allowed_file_types' => ['pdf'],
                'max_file_size' => 1024,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Clearance')
            ->assertJsonPath('data.allowed_file_types', ['pdf'])
            ->assertJsonPath('data.max_file_size', 1024);
    }

    public function test_staff_can_delete_a_requirement_without_documents(): void
    {
        $this->loginAsStaff();
        $requirement = Requirement::factory()->create();

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/requirements/{$requirement->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('requirements', ['id' => $requirement->id]);
    }

    public function test_staff_cannot_delete_a_requirement_referenced_by_documents(): void
    {
        $this->loginAsStaff();
        $requirement = Requirement::factory()->create();
        $application = Application::factory()->create();
        ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'requirement_id' => $requirement->id,
        ]);

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/requirements/{$requirement->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('requirements', ['id' => $requirement->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Students
    |--------------------------------------------------------------------------
    */

    public function test_student_can_view_requirements_of_a_published_cycle(): void
    {
        $this->loginAsStudent();
        $cycle = $this->cycle(['status' => 'upcoming']);
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->getJson("/api/program-cycles/{$cycle->id}/requirements")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $requirement->id)
            ->assertJsonPath('data.0.is_required', true);
    }

    public function test_student_cannot_view_requirements_of_a_draft_cycle(): void
    {
        $this->loginAsStudent();
        $cycle = $this->cycle(['status' => 'draft']);
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->getJson("/api/program-cycles/{$cycle->id}/requirements")
            ->assertStatus(404);
    }

    public function test_student_does_not_see_inactive_requirements(): void
    {
        $this->loginAsStudent();
        $cycle = $this->cycle(['status' => 'upcoming']);
        $active = Requirement::factory()->create(['is_active' => true]);
        $inactive = Requirement::factory()->create(['is_active' => false, 'slug' => 'inactive-req']);
        $cycle->requirements()->attach([$active->id, $inactive->id], ['is_required' => true]);

        $this->fromSpa()
            ->getJson("/api/program-cycles/{$cycle->id}/requirements")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_student_cannot_create_a_requirement(): void
    {
        $this->loginAsStudent();
        $cycle = $this->cycle();

        $this->fromSpa()
            ->postJson("/api/staff/catalog/cycles/{$cycle->id}/requirements", $this->requirementPayload())
            ->assertStatus(403);
    }

    public function test_student_cannot_update_or_delete_a_requirement(): void
    {
        $this->loginAsStudent();
        $cycle = $this->cycle();
        $requirement = Requirement::factory()->create();
        $cycle->requirements()->attach($requirement->id, ['is_required' => true]);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/cycles/{$cycle->id}/requirements/{$requirement->id}", [
                'name' => 'Hijacked',
                'slug' => 'hijacked',
            ])
            ->assertStatus(403);

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/cycles/{$cycle->id}/requirements/{$requirement->id}")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Security and edge cases
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_users_cannot_access_staff_requirement_endpoints(): void
    {
        $cycle = $this->cycle();

        $this->getJson("/api/staff/catalog/cycles/{$cycle->id}/requirements")
            ->assertStatus(401);
    }

    public function test_invalid_program_cycle_id_is_rejected(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/cycles/99999/requirements')
            ->assertStatus(404);
    }

    public function test_invalid_requirement_id_is_rejected(): void
    {
        $this->loginAsStaff();
        $cycle = $this->cycle();

        $this->fromSpa()
            ->deleteJson("/api/staff/catalog/cycles/{$cycle->id}/requirements/99999")
            ->assertStatus(404);
    }
}
