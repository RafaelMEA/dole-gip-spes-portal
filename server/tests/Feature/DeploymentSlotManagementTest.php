<?php

namespace Tests\Feature;

use App\Models\DeploymentSite;
use App\Models\DeploymentSlot;
use App\Models\HostAgency;
use App\Models\ProgramCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class DeploymentSlotManagementTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_list_deployment_slots(): void
    {
        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots')
            ->assertUnauthorized();
    }

    public function test_student_cannot_list_deployment_slots(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_deployment_slot(): void
    {
        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => 1,
                'deployment_site_id' => 1,
                'title' => 'Test',
                'capacity' => 5,
            ])
            ->assertUnauthorized();
    }

    public function test_student_cannot_create_deployment_slot(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => 1,
                'deployment_site_id' => 1,
                'title' => 'Test',
                'capacity' => 5,
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_view_deployment_slot(): void
    {
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}")
            ->assertUnauthorized();
    }

    public function test_student_cannot_view_deployment_slot(): void
    {
        $this->loginAsStudent();
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_update_deployment_slot(): void
    {
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/deployment-slots/{$slot->id}", [
                'title' => 'Updated',
                'capacity' => 10,
            ])
            ->assertUnauthorized();
    }

    public function test_student_cannot_update_deployment_slot(): void
    {
        $this->loginAsStudent();
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/deployment-slots/{$slot->id}", [
                'title' => 'Updated',
                'capacity' => 10,
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_toggle_deployment_slot_status(): void
    {
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/deployment-slots/{$slot->id}/status", ['status' => 'inactive'])
            ->assertUnauthorized();
    }

    public function test_student_cannot_toggle_deployment_slot_status(): void
    {
        $this->loginAsStudent();
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/deployment-slots/{$slot->id}/status", ['status' => 'inactive'])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | List (index)
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_list_deployment_slots(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();
        DeploymentSlot::factory()->count(3)->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_returns_paginated_response(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();
        DeploymentSlot::factory()->count(25)->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);

        $response = $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?per_page=10')
            ->assertOk();

        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonCount(10, 'data');
    }

    public function test_list_searches_by_title(): void
    {
        $this->loginAsStaff();
        DeploymentSlot::factory()->create(['title' => 'Administrative Assistant']);
        DeploymentSlot::factory()->create(['title' => 'IT Support']);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?search=administrative')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Administrative Assistant');
    }

    public function test_list_searches_by_deployment_site_name(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        $site1 = DeploymentSite::factory()->create(['name' => 'City Hall', 'host_agency_id' => $agency->id]);
        $site2 = DeploymentSite::factory()->create(['name' => 'PESO Office', 'host_agency_id' => $agency->id]);
        DeploymentSlot::factory()->create(['deployment_site_id' => $site1->id]);
        DeploymentSlot::factory()->create(['deployment_site_id' => $site2->id]);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?search=city hall')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_searches_by_host_agency_name(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create(['name' => 'Department of Agriculture']);
        $site = DeploymentSite::factory()->create(['host_agency_id' => $agency->id]);
        DeploymentSlot::factory()->create(['deployment_site_id' => $site->id]);

        $otherAgency = HostAgency::factory()->create(['name' => 'City Government']);
        $otherSite = DeploymentSite::factory()->create(['host_agency_id' => $otherAgency->id]);
        DeploymentSlot::factory()->create(['deployment_site_id' => $otherSite->id]);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?search=agriculture')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_filters_by_program_cycle(): void
    {
        $this->loginAsStaff();
        $cycle1 = ProgramCycle::factory()->create();
        $cycle2 = ProgramCycle::factory()->create();
        DeploymentSlot::factory()->count(2)->create(['program_cycle_id' => $cycle1->id]);
        DeploymentSlot::factory()->create(['program_cycle_id' => $cycle2->id]);

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots?program_cycle_id={$cycle1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_deployment_site(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site1 = DeploymentSite::factory()->create();
        $site2 = DeploymentSite::factory()->create();
        DeploymentSlot::factory()->count(2)->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site1->id]);
        DeploymentSlot::factory()->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site2->id]);

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots?deployment_site_id={$site1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_host_agency(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $agency1 = HostAgency::factory()->create();
        $agency2 = HostAgency::factory()->create();
        $site1 = DeploymentSite::factory()->create(['host_agency_id' => $agency1->id]);
        $site2 = DeploymentSite::factory()->create(['host_agency_id' => $agency2->id]);
        DeploymentSlot::factory()->count(2)->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site1->id]);
        DeploymentSlot::factory()->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site2->id]);

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots?host_agency_id={$agency1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_active_status(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();
        DeploymentSlot::factory()->active()->count(2)->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site->id]);
        DeploymentSlot::factory()->inactive()->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site->id]);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?status=active')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_inactive_status(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();
        DeploymentSlot::factory()->active()->count(2)->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site->id]);
        DeploymentSlot::factory()->inactive()->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site->id]);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_all_status_returns_all(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();
        DeploymentSlot::factory()->active()->count(2)->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site->id]);
        DeploymentSlot::factory()->inactive()->create(['program_cycle_id' => $cycle->id, 'deployment_site_id' => $site->id]);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?status=all')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_sorts_by_title_asc(): void
    {
        $this->loginAsStaff();
        DeploymentSlot::factory()->create(['title' => 'Zebra Position']);
        DeploymentSlot::factory()->create(['title' => 'Alpha Position']);

        $response = $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?sort=title&direction=asc')
            ->assertOk();

        $response->assertJsonPath('data.0.title', 'Alpha Position');
        $response->assertJsonPath('data.1.title', 'Zebra Position');
    }

    public function test_list_includes_program_cycle_and_site(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create(['name' => 'GIP 2026']);
        $agency = HostAgency::factory()->create(['name' => 'City Government']);
        $site = DeploymentSite::factory()->create(['name' => 'City Hall', 'host_agency_id' => $agency->id]);
        DeploymentSlot::factory()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots')
            ->assertOk()
            ->assertJsonPath('data.0.program_cycle.name', 'GIP 2026')
            ->assertJsonPath('data.0.deployment_site.name', 'City Hall');
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_view_deployment_slot(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create(['title' => 'IT Support']);

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'IT Support')
            ->assertJsonPath('data.id', $slot->id);
    }

    public function test_show_returns_404_for_nonexistent_slot(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots/9999')
            ->assertNotFound();
    }

    public function test_show_includes_program_cycle_and_site(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create(['name' => 'SPES 2026']);
        $site = DeploymentSite::factory()->create(['name' => 'Regional Office']);
        $slot = DeploymentSlot::factory()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);

        $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}")
            ->assertOk()
            ->assertJsonPath('data.program_cycle.name', 'SPES 2026')
            ->assertJsonPath('data.deployment_site.name', 'Regional Office');
    }

    /*
    |--------------------------------------------------------------------------
    | Create (store)
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_create_deployment_slot_with_all_fields(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Administrative Assistant',
                'description' => 'Administrative support position.',
                'capacity' => 5,
                'status' => 'active',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Administrative Assistant')
            ->assertJsonPath('data.capacity', 5)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.assigned_count', 0)
            ->assertJsonPath('data.available_count', 5);

        $this->assertDatabaseHas('deployment_slots', [
            'title' => 'Administrative Assistant',
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
            'capacity' => 5,
            'status' => 'active',
        ]);
    }

    public function test_staff_can_create_deployment_slot_with_minimal_fields(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Encoder',
                'capacity' => 3,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Encoder');
    }

    public function test_create_validates_program_cycle_id_is_required(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', ['title' => 'Test', 'deployment_site_id' => 1, 'capacity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['program_cycle_id']);
    }

    public function test_create_validates_program_cycle_id_exists(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => 9999,
                'deployment_site_id' => 1,
                'title' => 'Test',
                'capacity' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['program_cycle_id']);
    }

    public function test_create_validates_deployment_site_id_is_required(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', ['title' => 'Test', 'program_cycle_id' => 1, 'capacity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deployment_site_id']);
    }

    public function test_create_validates_deployment_site_id_exists(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => 1,
                'deployment_site_id' => 9999,
                'title' => 'Test',
                'capacity' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deployment_site_id']);
    }

    public function test_create_validates_title_is_required(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'capacity' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_validates_title_max_length(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => str_repeat('A', 256),
                'capacity' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_validates_capacity_is_required(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
    }

    public function test_create_validates_capacity_positive_integer(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => 1.5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
    }

    public function test_create_validates_status_enum(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => 5,
                'status' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_create_rejects_inactive_deployment_site(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->inactive()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => 5,
            ])
            ->assertStatus(422);
    }

    public function test_create_rejects_draft_program_cycle(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->draft()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => 5,
            ])
            ->assertStatus(422);
    }

    public function test_create_sets_status_active_by_default(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => 5,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_update_deployment_slot(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create(['title' => 'Old Title', 'capacity' => 5]);

        $this->fromSpa()
            ->putJson("/api/staff/deployment-slots/{$slot->id}", [
                'title' => 'New Title',
                'capacity' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.capacity', 10);

        $this->assertDatabaseHas('deployment_slots', [
            'id' => $slot->id,
            'title' => 'New Title',
            'capacity' => 10,
        ]);
    }

    public function test_update_returns_404_for_nonexistent_slot(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->putJson('/api/staff/deployment-slots/9999', [
                'title' => 'Updated',
                'capacity' => 5,
            ])
            ->assertNotFound();
    }

    public function test_update_validates_title_when_provided(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/deployment-slots/{$slot->id}", [
                'title' => '',
                'capacity' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_validates_capacity_when_provided(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/deployment-slots/{$slot->id}", [
                'capacity' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
    }

    public function test_update_does_not_require_all_fields(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create([
            'title' => 'Original Title',
            'capacity' => 5,
            'description' => 'Original description',
        ]);

        $this->fromSpa()
            ->putJson("/api/staff/deployment-slots/{$slot->id}", [
                'title' => 'Updated Title',
            ])
            ->assertOk();

        $this->assertDatabaseHas('deployment_slots', [
            'id' => $slot->id,
            'title' => 'Updated Title',
            'capacity' => 5,
            'description' => 'Original description',
        ]);
    }

    public function test_update_rejects_inactive_deployment_site(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create();
        $inactiveSite = DeploymentSite::factory()->inactive()->create();

        $this->fromSpa()
            ->putJson("/api/staff/deployment-slots/{$slot->id}", [
                'deployment_site_id' => $inactiveSite->id,
            ])
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Status toggle
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_deactivate_deployment_slot(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->active()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/deployment-slots/{$slot->id}/status", [
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('deployment_slots', [
            'id' => $slot->id,
            'status' => 'inactive',
        ]);
    }

    public function test_staff_can_activate_deployment_slot(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->inactive()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/deployment-slots/{$slot->id}/status", [
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('deployment_slots', [
            'id' => $slot->id,
            'status' => 'active',
        ]);
    }

    public function test_status_toggle_returns_404_for_nonexistent_slot(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson('/api/staff/deployment-slots/9999/status', [
                'status' => 'inactive',
            ])
            ->assertNotFound();
    }

    public function test_status_toggle_validates_status_is_required(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/deployment-slots/{$slot->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_status_toggle_validates_status_enum(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/deployment-slots/{$slot->id}/status", [
                'status' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function test_slot_belongs_to_correct_program_cycle(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create(['name' => 'GIP 2026']);
        $site = DeploymentSite::factory()->create();
        $slot = DeploymentSlot::factory()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);

        $response = $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}")
            ->assertOk();

        $response->assertJsonPath('data.program_cycle.name', 'GIP 2026');
        $response->assertJsonPath('data.program_cycle_id', $cycle->id);
    }

    public function test_slot_belongs_to_correct_deployment_site(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create(['name' => 'City Hall']);
        $slot = DeploymentSlot::factory()->create([
            'program_cycle_id' => $cycle->id,
            'deployment_site_id' => $site->id,
        ]);

        $response = $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}")
            ->assertOk();

        $response->assertJsonPath('data.deployment_site.name', 'City Hall');
        $response->assertJsonPath('data.deployment_site_id', $site->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Capacity
    |--------------------------------------------------------------------------
    */

    public function test_capacity_is_positive_integer(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => 5,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.capacity', 5);
    }

    public function test_assigned_count_is_derived(): void
    {
        $this->loginAsStaff();
        $slot = DeploymentSlot::factory()->create(['capacity' => 10]);

        $response = $this->fromSpa()
            ->getJson("/api/staff/deployment-slots/{$slot->id}")
            ->assertOk();

        $response->assertJsonPath('data.assigned_count', 0);
        $response->assertJsonPath('data.available_count', 10);
    }

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    public function test_mass_assignment_protected(): void
    {
        $this->loginAsStaff();
        $cycle = ProgramCycle::factory()->create();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/deployment-slots', [
                'program_cycle_id' => $cycle->id,
                'deployment_site_id' => $site->id,
                'title' => 'Test',
                'capacity' => 5,
                'id' => 9999,
                'created_at' => '2000-01-01',
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('deployment_slots', 1);
        $this->assertDatabaseMissing('deployment_slots', ['id' => 9999]);
    }

    public function test_arbitrary_sorting_prevented(): void
    {
        $this->loginAsStaff();
        DeploymentSlot::factory()->count(2)->create();

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?sort=DROP TABLE&direction=asc')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_pagination_limits_enforced(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20);

        $this->fromSpa()
            ->getJson('/api/staff/deployment-slots?per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }
}
