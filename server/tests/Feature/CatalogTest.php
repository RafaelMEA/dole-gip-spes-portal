<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Requirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    public function test_any_authenticated_user_can_browse_programs(): void
    {
        $this->loginAsStudent();
        Program::factory()->gip()->create();

        $this->fromSpa()
            ->getJson('/api/programs')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_can_create_a_program_cycle(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();
        $requirement = Requirement::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', [
                'program_id' => $program->id,
                'name' => 'GIP Test Batch',
                'total_slots' => 15,
                'application_start' => now()->toDateString(),
                'application_deadline' => now()->addMonth()->toDateString(),
                'requirements' => [$requirement->id],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'GIP Test Batch')
            ->assertJsonPath('data.slots_remaining', 15);

        $this->assertDatabaseHas('program_cycles', ['name' => 'GIP Test Batch']);
        $this->assertDatabaseHas('program_cycle_requirements', [
            'requirement_id' => $requirement->id,
        ]);
    }

    public function test_staff_can_create_a_host_agency(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Local Government Unit',
                'address' => 'Municipal Hall',
                'contact_person' => 'A. Reyes',
                'email' => 'lgu@example.com',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Local Government Unit');
    }

    public function test_staff_can_create_a_deployment_site_and_requirement(): void
    {
        $this->loginAsStaff();
        $agency = \App\Models\HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => 'Municipal Hall',
                'city' => 'San Fernando',
            ])
            ->assertStatus(201);

        $this->fromSpa()
            ->postJson('/api/staff/catalog/requirements', [
                'name' => 'Clearance',
                'slug' => 'clearance',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'clearance');
    }

    public function test_a_student_cannot_manage_catalog(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/programs', ['name' => 'X', 'slug' => 'x'])
            ->assertStatus(403);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies')
            ->assertStatus(403);
    }

    public function test_cycle_deadline_cannot_precede_start(): void
    {
        $this->loginAsStaff();
        $program = Program::factory()->gip()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/cycles', [
                'program_id' => $program->id,
                'name' => 'Bad Batch',
                'total_slots' => 10,
                'application_start' => now()->addMonth()->toDateString(),
                'application_deadline' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('application_deadline');
    }
}
