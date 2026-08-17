<?php

namespace Tests\Feature;

use App\Models\HostAgency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class HostAgencyManagementTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_list_host_agencies(): void
    {
        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies')
            ->assertUnauthorized();
    }

    public function test_student_cannot_list_host_agencies(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies')
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_host_agency(): void
    {
        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', ['name' => 'Test'])
            ->assertUnauthorized();
    }

    public function test_student_cannot_create_host_agency(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', ['name' => 'Test'])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_view_host_agency(): void
    {
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->getJson("/api/staff/catalog/host-agencies/{$agency->id}")
            ->assertUnauthorized();
    }

    public function test_student_cannot_view_host_agency(): void
    {
        $this->loginAsStudent();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->getJson("/api/staff/catalog/host-agencies/{$agency->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_update_host_agency(): void
    {
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/host-agencies/{$agency->id}", ['name' => 'Updated'])
            ->assertUnauthorized();
    }

    public function test_student_cannot_update_host_agency(): void
    {
        $this->loginAsStudent();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/host-agencies/{$agency->id}", ['name' => 'Updated'])
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_toggle_host_agency_status(): void
    {
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/host-agencies/{$agency->id}/status", ['is_active' => false])
            ->assertUnauthorized();
    }

    public function test_student_cannot_toggle_host_agency_status(): void
    {
        $this->loginAsStudent();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/host-agencies/{$agency->id}/status", ['is_active' => false])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | List (index)
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_list_host_agencies(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->count(3)->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_returns_paginated_response(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->count(25)->create();

        $response = $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?per_page=10')
            ->assertOk();

        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonCount(10, 'data');
    }

    public function test_list_searches_by_name(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->create(['name' => 'Department of Agriculture']);
        HostAgency::factory()->create(['name' => 'City Hospital']);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?search=agriculture')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Department of Agriculture');
    }

    public function test_list_searches_by_contact_person(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->create(['contact_person' => 'Juan Dela Cruz']);
        HostAgency::factory()->create(['contact_person' => 'Maria Clara']);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?search=juan')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_searches_by_email(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->create(['email' => 'office@example.com']);
        HostAgency::factory()->create(['email' => 'hospital@example.com']);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?search=office')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_filters_by_active_status(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->active()->count(2)->create();
        HostAgency::factory()->inactive()->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?status=active')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_inactive_status(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->active()->count(2)->create();
        HostAgency::factory()->inactive()->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_all_status_returns_all(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->active()->count(2)->create();
        HostAgency::factory()->inactive()->create();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?status=all')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_sorts_by_name_asc(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->create(['name' => 'Zebra Agency']);
        HostAgency::factory()->create(['name' => 'Alpha Agency']);

        $response = $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?sort=name&direction=asc')
            ->assertOk();

        $response->assertJsonPath('data.0.name', 'Alpha Agency');
        $response->assertJsonPath('data.1.name', 'Zebra Agency');
    }

    public function test_list_sorts_by_created_at_desc(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->create(['name' => 'Old Agency', 'created_at' => now()->subDays(5)]);
        HostAgency::factory()->create(['name' => 'New Agency', 'created_at' => now()]);

        $response = $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies?sort=created_at&direction=desc')
            ->assertOk();

        $response->assertJsonPath('data.0.name', 'New Agency');
        $response->assertJsonPath('data.1.name', 'Old Agency');
    }

    public function test_list_includes_active_assignments_count(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->create();

        $response = $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies')
            ->assertOk();

        $response->assertJsonPath('data.0.active_assignments', 0);
    }

    public function test_list_includes_agency_type_and_label(): void
    {
        $this->loginAsStaff();
        HostAgency::factory()->create(['agency_type' => 'government']);

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies')
            ->assertOk()
            ->assertJsonPath('data.0.agency_type', 'government')
            ->assertJsonPath('data.0.agency_type_label', 'Government');
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_view_host_agency(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create(['name' => 'Test Agency']);

        $this->fromSpa()
            ->getJson("/api/staff/catalog/host-agencies/{$agency->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Test Agency')
            ->assertJsonPath('data.id', $agency->id);
    }

    public function test_show_returns_404_for_nonexistent_agency(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->getJson('/api/staff/catalog/host-agencies/9999')
            ->assertNotFound();
    }

    public function test_show_includes_active_assignments_count(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->getJson("/api/staff/catalog/host-agencies/{$agency->id}")
            ->assertOk()
            ->assertJsonPath('data.active_assignments', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Create (store)
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_create_host_agency_with_all_fields(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Department of Agriculture',
                'agency_type' => 'government',
                'address' => 'National Capital Region',
                'contact_person' => 'Juan Dela Cruz',
                'contact_number' => '09171234567',
                'email' => 'da@example.com',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Department of Agriculture')
            ->assertJsonPath('data.agency_type', 'government')
            ->assertJsonPath('data.agency_type_label', 'Government')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('host_agencies', [
            'name' => 'Department of Agriculture',
            'agency_type' => 'government',
            'email' => 'da@example.com',
            'is_active' => true,
        ]);
    }

    public function test_staff_can_create_host_agency_with_minimal_fields(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Minimal Agency',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Minimal Agency')
            ->assertJsonPath('data.agency_type', null);
    }

    public function test_create_validates_name_is_required(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_validates_name_max_length(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => str_repeat('A', 256),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_validates_agency_type_enum(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Test Agency',
                'agency_type' => 'invalid_type',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agency_type']);
    }

    public function test_create_accepts_all_valid_agency_types(): void
    {
        $this->loginAsStaff();

        foreach (['government', 'private', 'ngo', 'other'] as $type) {
            $this->fromSpa()
                ->postJson('/api/staff/catalog/host-agencies', [
                    'name' => "Agency {$type}",
                    'agency_type' => $type,
                ])
                ->assertStatus(201)
                ->assertJsonPath('data.agency_type', $type);
        }
    }

    public function test_create_validates_email_format(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Test Agency',
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_validates_address_max_length(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Test Agency',
                'address' => str_repeat('A', 501),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address']);
    }

    public function test_create_validates_contact_number_max_length(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Test Agency',
                'contact_number' => str_repeat('9', 51),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_number']);
    }

    public function test_create_sets_is_active_true_by_default(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Active Agency',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('host_agencies', [
            'name' => 'Active Agency',
            'is_active' => true,
        ]);
    }

    public function test_create_can_set_is_active_false(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->postJson('/api/staff/catalog/host-agencies', [
                'name' => 'Inactive Agency',
                'is_active' => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('host_agencies', [
            'name' => 'Inactive Agency',
            'is_active' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_update_host_agency(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create(['name' => 'Old Name']);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/host-agencies/{$agency->id}", [
                'name' => 'New Name',
                'address' => 'Updated Address',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('host_agencies', [
            'id' => $agency->id,
            'name' => 'New Name',
            'address' => 'Updated Address',
        ]);
    }

    public function test_update_returns_404_for_nonexistent_agency(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->putJson('/api/staff/catalog/host-agencies/9999', [
                'name' => 'Updated',
            ])
            ->assertNotFound();
    }

    public function test_update_validates_name_when_provided(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/host-agencies/{$agency->id}", [
                'name' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validates_agency_type_enum(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/host-agencies/{$agency->id}", [
                'agency_type' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agency_type']);
    }

    public function test_update_validates_email_format(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->putJson("/api/staff/catalog/host-agencies/{$agency->id}", [
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_does_not_require_all_fields(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create([
            'name' => 'Original Name',
            'address' => 'Original Address',
        ]);

        $this->fromSpa()
            ->putJson("/api/staff/catalog/host-agencies/{$agency->id}", [
                'name' => 'Updated Name',
            ])
            ->assertOk();

        $this->assertDatabaseHas('host_agencies', [
            'id' => $agency->id,
            'name' => 'Updated Name',
            'address' => 'Original Address',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Status toggle
    |--------------------------------------------------------------------------
    */

    public function test_staff_can_deactivate_host_agency(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->active()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/host-agencies/{$agency->id}/status", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('host_agencies', [
            'id' => $agency->id,
            'is_active' => false,
        ]);
    }

    public function test_staff_can_activate_host_agency(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->inactive()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/host-agencies/{$agency->id}/status", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('host_agencies', [
            'id' => $agency->id,
            'is_active' => true,
        ]);
    }

    public function test_status_toggle_returns_404_for_nonexistent_agency(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->patchJson('/api/staff/catalog/host-agencies/9999/status', [
                'is_active' => false,
            ])
            ->assertNotFound();
    }

    public function test_status_toggle_validates_is_active_is_required(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/host-agencies/{$agency->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_status_toggle_validates_is_active_is_boolean(): void
    {
        $this->loginAsStaff();
        $agency = HostAgency::factory()->create();

        $this->fromSpa()
            ->patchJson("/api/staff/catalog/host-agencies/{$agency->id}/status", [
                'is_active' => 'not-a-boolean',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }
}
