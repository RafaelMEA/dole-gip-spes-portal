<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    public function test_a_student_can_view_their_profile(): void
    {
        $student = $this->loginAsStudent();
        $student->studentDetail()->create([
            'school_name' => 'DHVSU',
            'course' => 'BSIT',
            'year_level' => 3,
            'gwa' => 1.75,
            'is_indigent' => true,
            'is_4ps_member' => false,
        ]);

        $this->fromSpa()
            ->getJson('/api/student/profile')
            ->assertOk()
            ->assertJsonPath('data.student_details.school_name', 'DHVSU')
            ->assertJsonPath('data.student_details.year_level', '3');
    }

    public function test_a_student_can_update_their_profile(): void
    {
        $student = $this->loginAsStudent();

        $this->fromSpa()
            ->putJson('/api/student/profile', [
                'name' => 'New Name',
                'school_name' => 'Bulacan State University',
                'course' => 'BSBA',
                'year_level' => '2',
                'gwa' => '1.90',
                'address' => 'Pampanga',
                'birthplace' => 'Bulacan',
                'birthdate' => '2004-01-01',
                'sex' => 'female',
                'is_indigent' => true,
                'is_4ps_member' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.student_details.school_name', 'Bulacan State University')
            ->assertJsonPath('data.student_details.year_level', '2');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'New Name']);
        $this->assertDatabaseHas('student_details', [
            'user_id' => $student->id,
            'school_name' => 'Bulacan State University',
            'is_4ps_member' => true,
        ]);
    }

    public function test_profile_validation_rejects_invalid_year_level(): void
    {
        $this->loginAsStudent();

        $this->fromSpa()
            ->putJson('/api/student/profile', [
                'name' => 'Student',
                'school_name' => 'University',
                'course' => 'Course',
                'year_level' => '9',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('year_level');
    }

    public function test_staff_cannot_access_student_profile(): void
    {
        $this->loginAsStaff();

        $this->fromSpa()
            ->getJson('/api/student/profile')
            ->assertStatus(403);
    }
}
