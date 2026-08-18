<?php

namespace Tests\Feature;

use App\Models\DeploymentSite;
use App\Models\HostAgency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class DeploymentSiteManagementTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_list_deployment_sites(): void
    {
        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites')
            ->assertUnauthorized();
    }

    public function test_student_cannot_list_deployment_sites(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_deployment_site(): void
    {
        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', ['name' => 'Test', 'host_agency_id' => 1])
            ->assertUnauthorized();
    }

    public function test_student_cannot_create_deployment_site(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', ['name' => 'Test', 'host_agency_id' => 1])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_view_deployment_site(): void
    {
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->getJson("/api/staff/catalog/deployment-sites/{$site->id}")
            ->assertUnauthorized();
    }

    public function test_student_cannot_view_deployment_site(): void
    {
        $this->loginAsStudent();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->getJson("/api/staff/catalog/deployment-sites/{$site->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_update_deployment_site(): void
    {
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/deployment-sites/{$site->id}", ['name' => 'Updated', 'host_agency_id' => $site->host_agency_id])
            ->assertUnauthorized();
    }

    public function test_student_cannot_update_deployment_site(): void
    {
        $this->loginAsStudent();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/deployment-sites/{$site->id}", ['name' => 'Updated', 'host_agency_id' => $site->host_agency_id])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_toggle_deployment_site_status(): void
    {
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/deployment-sites/{$site->id}/status", ['is_active' => false])
            ->assertUnauthorized();
    }

    public function test_student_cannot_toggle_deployment_site_status(): void
    {
        $this->loginAsStudent();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/deployment-sites/{$site->id}/status", ['is_active' => false])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | List (index)
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_list_deployment_sites(): void
    {
        $this->loginAsStaff();
        DeploymentSite::factory()->count(3)->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_returns_paginated_response(): void
    {
        $this->loginAsStaff();
        DeploymentSite::factory()->count(25)->create();

        $response = $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?per_page=10')
            ->assertOk();

        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonCount(10, 'data');
    }

    public function test_list_searches_by_name(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        DeploymentSite::factory()->create(['name' => 'City Hall Main Building', 'host_agency_id' => $agency->id]);
        DeploymentSite::factory()->create(['name' => 'Municipal Hall', 'host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?search=city hall')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'City Hall Main Building');
    }

    public function test_list_searches_by_address(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        DeploymentSite::factory()->create(['address' => '123 Main St', 'host_agency_id' => $agency->id]);
        DeploymentSite::factory()->create(['address' => '456 Oak Ave', 'host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?search=123')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_searches_by_contact_person(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        DeploymentSite::factory()->create(['contact_person' => 'Juan Dela Cruz', 'host_agency_id' => $agency->id]);
        DeploymentSite::factory()->create(['contact_person' => 'Maria Clara', 'host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?search=juan')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_searches_by_host_agency_name(): void
    {
        $this->loginAsStaff();
        $agency1 = HostAgency::factory()->create(['name' => 'Department of Agriculture']);
        $agency2 = HostAgency::factory()->create(['name' => 'City Government']);
        DeploymentSite::factory()->create(['name' => 'Regional Office', 'host_agency_id' => $agency1->id]);
        DeploymentSite::factory()->create(['name' => 'City Hall', 'host_agency_id' => $agency2->id]);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?search=agriculture')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_filters_by_host_agency(): void
    {
        $this->loginAsStaff();
        $agency1 = HostAgency::factory()->create();
        $agency2 = HostAgency::factory()->create();
        DeploymentSite::factory()->count(2)->create(['host_agency_id' => $agency1->id]);
        DeploymentSite::factory()->create(['host_agency_id' => $agency2->id]);

        $this->fromSpa()
            ->getJson("/api/staff/catalog/deployment-sites?host_agency_id={$agency1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_active_status(): void
    {
        $this->loginAsStaff();
        DeploymentSite::factory()->active()->count(2)->create();
        DeploymentSite::factory()->inactive()->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?status=active')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_inactive_status(): void
    {
        $this->loginAsStaff();
        DeploymentSite::factory()->active()->count(2)->create();
        DeploymentSite::factory()->inactive()->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_all_status_returns_all(): void
    {
        $this->loginAsStaff();
        DeploymentSite::factory()->active()->count(2)->create();
        DeploymentSite::factory()->inactive()->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?status=all')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_sorts_by_name_asc(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        DeploymentSite::factory()->create(['name' => 'Zebra Site', 'host_agency_id' => $agency->id]);
        DeploymentSite::factory()->create(['name' => 'Alpha Site', 'host_agency_id' => $agency->id]);

        $response = $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?sort=name&direction=asc')
            ->assertOk();

        $response->assertJsonPath('data.0.name', 'Alpha Site');
        $response->assertJsonPath('data.1.name', 'Zebra Site');
    }

    public function test_list_sorts_by_created_at_desc(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        DeploymentSite::factory()->create(['name' => 'Old Site', 'host_agency_id' => $agency->id, 'created_at' => now()->subDays(5)]);
        DeploymentSite::factory()->create(['name' => 'New Site', 'host_agency_id' => $agency->id, 'created_at' => now()]);

        $response = $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites?sort=created_at&direction=desc')
            ->assertOk();

        $response->assertJsonPath('data.0.name', 'New Site');
        $response->assertJsonPath('data.1.name', 'Old Site');
    }

    public function test_list_includes_host_agency(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create(['name' => 'DA Regional Office']);
        DeploymentSite::factory()->create(['host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites')
            ->assertOk()
            ->assertJsonPath('data.0.host_agency.name', 'DA Regional Office');
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_view_deployment_site(): void
    {
        $this->loginAsStaff();
        $site = DeploymentSite::factory()->create(['name' => 'City Hall']);

        $this->fromSpa()
            ->getJson("/api/staff/catalog/deployment-sites/{$site->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'City Hall')
            ->assertJsonPath('data.id', $site->id);
    }

    public function test_show_returns_404_for_nonexistent_site(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/deployment-sites/9999')
            ->assertNotFound();
    }

    public function test_show_includes_host_agency(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create(['name' => 'DA']);
        $site = DeploymentSite::factory()->create(['host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->getJson("/api/staff/catalog/deployment-sites/{$site->id}")
            ->assertOk()
            ->assertJsonPath('data.host_agency.name', 'DA');
    }

    /*
    |--------------------------------------------------------------------------
    | Create (store)
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_create_deployment_site_with_all_fields(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => 'City Hall Main Building',
                'address' => '123 Main St',
                'city' => 'San Fernando',
                'region' => 'Region III',
                'contact_person' => 'Juan Dela Cruz',
                'contact_number' => '09171234567',
                'email' => 'cityhall@example.com',
                'description' => 'Main city government office.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'City Hall Main Building')
            ->assertJsonPath('data.host_agency_id', $agency->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('deployment_sites', [
            'name' => 'City Hall Main Building',
            'host_agency_id' => $agency->id,
            'email' => 'cityhall@example.com',
            'is_active' => true,
        ]);
    }

    public function test_staff_can_create_deployment_site_with_minimal_fields(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => 'Minimal Site',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Minimal Site');
    }

    public function test_create_validates_host_agency_id_is_required(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', ['name' => 'Test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['host_agency_id']);
    }

    public function test_create_validates_host_agency_id_exists(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'name' => 'Test',
                'host_agency_id' => 9999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['host_agency_id']);
    }

    public function test_create_validates_name_is_required(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', ['host_agency_id' => $agency->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_validates_name_max_length(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => str_repeat('A', 256),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_validates_email_format(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => 'Test Site',
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_validates_address_max_length(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => 'Test Site',
                'address' => str_repeat('A', 501),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address']);
    }

    public function test_create_validates_description_max_length(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => 'Test Site',
                'description' => str_repeat('A', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_create_sets_is_active_true_by_default(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => 'Active Site',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('deployment_sites', [
            'name' => 'Active Site',
            'is_active' => true,
        ]);
    }

    public function test_create_can_set_is_active_false(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/deployment-sites', [
                'host_agency_id' => $agency->id,
                'name' => 'Inactive Site',
                'is_active' => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('deployment_sites', [
            'name' => 'Inactive Site',
            'is_active' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_update_deployment_site(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        $site = DeploymentSite::factory()->create(['name' => 'Old Name', 'host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/deployment-sites/{$site->id}", [
                'host_agency_id' => $agency->id,
                'name' => 'New Name',
                'address' => 'Updated Address',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('deployment_sites', [
            'id' => $site->id,
            'name' => 'New Name',
            'address' => 'Updated Address',
        ]);
    }

    public function test_update_returns_404_for_nonexistent_site(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->putJson('/api/staff/catalog/deployment-sites/9999', [
                'host_agency_id' => $agency->id,
                'name' => 'Updated',
            ])
            ->assertNotFound();
    }

    public function test_update_validates_name_when_provided(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        $site = DeploymentSite::factory()->create(['host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/deployment-sites/{$site->id}", [
                'host_agency_id' => $agency->id,
                'name' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validates_email_format(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        $site = DeploymentSite::factory()->create(['host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/deployment-sites/{$site->id}", [
                'host_agency_id' => $agency->id,
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_does_not_require_all_fields(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        $site = DeploymentSite::factory()->create([
            'name' => 'Original Name',
            'address' => 'Original Address',
            'host_agency_id' => $agency->id,
        ]);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/deployment-sites/{$site->id}", [
                'host_agency_id' => $agency->id,
                'name' => 'Updated Name',
            ])
            ->assertOk();

        $this->assertDatabaseHas('deployment_sites', [
            'id' => $site->id,
            'name' => 'Updated Name',
            'address' => 'Original Address',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Status toggle
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_deactivate_deployment_site(): void
    {
        $this->loginAsStaff();
        $site = DeploymentSite::factory()->active()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/deployment-sites/{$site->id}/status", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('deployment_sites', [
            'id' => $site->id,
            'is_active' => false,
        ]);
    }

    public function test_staff_can_activate_deployment_site(): void
    {
        $this->loginAsStaff();
        $site = DeploymentSite::factory()->inactive()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/deployment-sites/{$site->id}/status", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('deployment_sites', [
            'id' => $site->id,
            'is_active' => true,
        ]);
    }

    public function test_status_toggle_returns_404_for_nonexistent_site(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson('/api/staff/catalog/deployment-sites/9999/status', [
                'is_active' => false,
            ])
            ->assertNotFound();
    }

    public function test_status_toggle_validates_is_active_is_required(): void
    {
        $this->loginAsStaff();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/deployment-sites/{$site->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_status_toggle_validates_is_active_is_boolean(): void
    {
        $this->loginAsStaff();
        $site = DeploymentSite::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/deployment-sites/{$site->id}/status", [
                'is_active' => 'not-a-boolean',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function test_site_belongs_to_correct_host_agency(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create(['name' => 'DA Regional']);
        $site = DeploymentSite::factory()->create(['host_agency_id' => $agency->id]);

        $response = $this->fromSpa()
            ->getJson("/api/staff/catalog/deployment-sites/{$site->id}")
            ->assertOk();

        $response->assertJsonPath('data.host_agency.name', 'DA Regional');
        $response->assertJsonPath('data.host_agency_id', $agency->id);
    }

    public function test_host_agency_resource_includes_deployment_sites_count(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();
        DeploymentSite::factory()->count(3)->create(['host_agency_id' => $agency->id]);

        $this->fromSpa()
            ->getJson("/api/staff/catalog/host-agencies/{$agency->id}")
            ->assertOk();
    }
}
