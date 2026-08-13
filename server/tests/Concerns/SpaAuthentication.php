<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

trait SpaAuthentication
{
    private const FRONTEND_ORIGIN = 'http://localhost:5173';

    /**
     * Simulate a first-party SPA request so Sanctum treats it as stateful
     * (starts the session, enables cookie/session authentication).
     */
    private function fromSpa(): static
    {
        // The framework shares one container across all test HTTP calls, so
        // guards cache the first user they ever resolved. Drop that state so
        // each request authenticates against the current session instead.
        Auth::forgetGuards();

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

    private function loginAs(User $user): TestResponse
    {
        return $this->fromSpa()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
    }

    private function loginAsEmail(string $email): TestResponse
    {
        return $this->fromSpa()->postJson('/api/login', [
            'email' => $email,
            'password' => 'password',
        ]);
    }

    /**
     * Create a student and log them in.
     */
    private function loginAsStudent(): User
    {
        $student = User::factory()->student()->create();
        $this->loginAs($student)->assertOk();

        return $student;
    }

    /**
     * Create a staff member and log them in.
     */
    private function loginAsStaff(): User
    {
        $staff = User::factory()->staff()->create();
        $this->loginAs($staff)->assertOk();

        return $staff;
    }
}
