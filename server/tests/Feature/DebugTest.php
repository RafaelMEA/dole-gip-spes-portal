<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'http://localhost:5173';
    private const LOG = '/tmp/opencode/debug.log';

    private function fromSpa(): static
    {
        return $this->withHeader('Origin', self::FRONTEND_ORIGIN)
            ->withHeader('Referer', self::FRONTEND_ORIGIN.'/');
    }

    private function log(string $msg, mixed $val): void
    {
        file_put_contents(self::LOG, $msg.': '.var_export($val, true)."\n", FILE_APPEND);
    }

    public function test_debug_logout_replica(): void
    {
        @unlink(self::LOG);
        $user = User::factory()->create(['password' => 'Secret123']);

        $this->fromSpa()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secret123',
        ])->assertStatus(200);

        $this->assertAuthenticated();
        $this->log('after login guard check', $this->app['auth']->guard('web')->check());

        $this->fromSpa()
            ->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJsonPath('message', 'You have been signed out.');

        $guard = $this->app['auth']->guard('web');
        $this->log('after logout guard check', $guard->check());
        $this->log('after logout guard id', $guard->id());
        $ref = new \ReflectionProperty($guard, 'loggedOut');
        $ref->setAccessible(true);
        $this->log('after logout loggedOut', $ref->getValue($guard));
        $this->log('session has login key', session()->has('login_web_'.sha1('web')));
        $this->log('session id', session()->getId());

        try {
            $this->assertGuest();
            $this->log('assertGuest', 'PASSED');
        } catch (\Throwable $e) {
            $this->log('assertGuest', 'FAILED: '.$e->getMessage());
        }

        $g2 = $this->app->make('auth')->guard('web');
        $ref2 = new \ReflectionProperty($g2, 'loggedOut');
        $ref2->setAccessible(true);
        $this->log('make-auth same instance as guard', $g2 === $guard);
        $this->log('make-auth check', $g2->check());
        $this->log('make-auth user id', $g2->id());
        $this->log('make-auth loggedOut', $ref2->getValue($g2));
        $this->log('isAuthenticated', $this->isAuthenticated('web'));

        $resp = $this->fromSpa()->getJson('/api/user');
        $this->log('post-logout /api/user status', $resp->status());
        $req = $this->app['request'];
        $this->log('post-logout request session id', $req->getSession()->getId());
        $this->log('post-logout request session all', $req->getSession()->all());
        $this->log('post-logout request user', $req->user()?->id ?? 'null');
        $this->log('post-logout cookies', $req->cookies->all());
    }
}
