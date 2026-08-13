<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'http://localhost:5173';

    /**
     * Simulate a first-party SPA request so Sanctum treats it as stateful
     * (starts the session, enables cookie/session authentication).
     */
    private function fromSpa(): static
    {
        return $this->withHeader('Origin', self::FRONTEND_ORIGIN)
            ->withHeader('Referer', self::FRONTEND_ORIGIN.'/');
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ], $overrides);
    }

    public function test_guest_cannot_access_authenticated_endpoints(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_a_student_can_register(): void
    {
        $response = $this->fromSpa()
            ->postJson('/api/register', $this->registerPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.email', 'juan@example.com')
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.name', 'Juan Dela Cruz')
            ->assertJsonMissing(['password' => 'Secret123'])
            ->assertJsonMissing(['remember_token' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role' => 'student',
        ]);

        $user = User::where('email', 'juan@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotSame('Secret123', $user->password);
        $this->assertTrue(Hash::check('Secret123', $user->password));
    }

    public function test_registration_does_not_authenticate_the_user(): void
    {
        $this->fromSpa()
            ->postJson('/api/register', $this->registerPayload())
            ->assertStatus(201);

        $this->assertGuest();
        $this->assertFalse($this->isAuthenticated('web'));
    }

    public function test_role_is_never_taken_from_the_registration_payload(): void
    {
        $this->fromSpa()
            ->postJson('/api/register', $this->registerPayload(['role' => 'staff']))
            ->assertStatus(201)
            ->assertJsonPath('data.role', 'student');

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role' => 'student',
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'juan@example.com']);

        $this->fromSpa()
            ->postJson('/api/register', $this->registerPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->fromSpa()
            ->postJson('/api/register', $this->registerPayload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->fromSpa()
            ->postJson('/api/register', $this->registerPayload(['password' => 'short']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_password_mismatch_is_rejected(): void
    {
        $this->fromSpa()
            ->postJson('/api/register', $this->registerPayload(['password_confirmation' => 'Different99']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_registration_requires_name_email_and_password(): void
    {
        $this->fromSpa()
            ->postJson('/api/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_a_user_can_login_and_access_their_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'juan@example.com',
            'password' => 'Secret123',
        ]);

        $this->fromSpa()
            ->postJson('/api/login', [
                'email' => 'juan@example.com',
                'password' => 'Secret123',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'juan@example.com')
            ->assertJsonMissing(['password']);

        $this->assertAuthenticated();

        $this->fromSpa()
            ->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'juan@example.com')
            ->assertJsonPath('data.role', 'student')
            ->assertJsonMissing(['password']);
    }

    public function test_login_with_invalid_credentials_returns_a_generic_error(): void
    {
        $user = User::factory()->create([
            'email' => 'juan@example.com',
            'password' => 'Secret123',
        ]);

        $this->fromSpa()
            ->postJson('/api/login', [
                'email' => 'juan@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->fromSpa()
            ->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        RateLimiter::clear('login');

        $user = User::factory()->create([
            'email' => 'juan@example.com',
            'password' => 'Secret123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->fromSpa()->postJson('/api/login', [
                'email' => 'juan@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->fromSpa()
            ->postJson('/api/login', [
                'email' => 'juan@example.com',
                'password' => 'Secret123',
            ])
            ->assertStatus(429);
    }

    public function test_a_user_can_logout_and_the_session_is_invalidated(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $this->fromSpa()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secret123',
        ])->assertStatus(200);

        $this->assertAuthenticated();

        $this->fromSpa()
            ->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJsonPath('message', 'You have been signed out.');

        $this->assertGuest();

        $this->fromSpa()->getJson('/api/user')->assertStatus(401);
    }

    public function test_student_cannot_access_staff_endpoints(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'Secret123']);

        $this->fromSpa()->postJson('/api/login', [
            'email' => $student->email,
            'password' => 'Secret123',
        ])->assertStatus(200);

        $this->fromSpa()
            ->getJson('/api/staff/dashboard')
            ->assertStatus(403);
    }

    public function test_staff_cannot_access_student_endpoints(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'Secret123']);

        $this->fromSpa()->postJson('/api/login', [
            'email' => $staff->email,
            'password' => 'Secret123',
        ])->assertStatus(200);

        $this->fromSpa()
            ->getJson('/api/student/dashboard')
            ->assertStatus(403);
    }

    public function test_student_can_access_student_endpoints(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'Secret123']);

        $this->fromSpa()->postJson('/api/login', [
            'email' => $student->email,
            'password' => 'Secret123',
        ])->assertStatus(200);

        $this->fromSpa()->getJson('/api/student/dashboard')->assertStatus(200);
    }

    public function test_staff_can_access_staff_endpoints(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'Secret123']);

        $this->fromSpa()->postJson('/api/login', [
            'email' => $staff->email,
            'password' => 'Secret123',
        ])->assertStatus(200);

        $this->fromSpa()->getJson('/api/staff/dashboard')->assertStatus(200);
    }

    public function test_password_is_stored_hashed_not_plaintext(): void
    {
        User::factory()->create([
            'email' => 'hash@example.com',
            'password' => 'Secret123',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'hash@example.com',
            'password' => 'Secret123',
        ]);

        $user = User::where('email', 'hash@example.com')->first();
        $this->assertTrue(Hash::check('Secret123', $user->password));
    }
}
