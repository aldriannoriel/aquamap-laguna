<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_guests_cannot_access_any_admin_resource_or_write_action(): void
    {
        foreach (['/admin', '/admin/reports', '/admin/reports/1', '/admin/reports/1/photo'] as $url) {
            $this->get($url)->assertRedirectToRoute('admin.login');
        }
        $this->patch('/admin/reports/1/status', [])->assertRedirectToRoute('admin.login');
        $this->post('/admin/logout')->assertRedirectToRoute('admin.login');
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_login_accepts_only_an_administrator_and_regenerates_session(): void
    {
        $password = Str::random(24).'aA1!';
        $admin = User::factory()->create(['password' => $password]);
        $admin->forceFill(['is_admin' => true])->save();
        $this->get(route('admin.login'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $sessionId = session()->getId();
        $this->post(route('admin.login.store'), ['email' => strtoupper($admin->email), 'password' => $password, 'is_admin' => false])
            ->assertRedirectToRoute('admin.dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($sessionId, session()->getId());
        $this->get(route('admin.login'))->assertRedirectToRoute('admin.dashboard');
    }

    public function test_a_valid_nonadministrator_password_cannot_log_in_or_promote_the_user(): void
    {
        $password = Str::random(24);
        $user = User::factory()->create(['password' => $password]);
        $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => $password, 'is_admin' => true])
            ->assertSessionHasErrors('email')->assertRedirectToRoute('admin.login');
        $this->assertGuest();
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('is_admin', session()->getOldInput());
    }

    public function test_passwords_are_not_repopulated_and_array_inputs_do_not_break_login(): void
    {
        $this->post(route('admin.login.store'), ['email' => ['invalid'], 'password' => 'not-a-credential'])
            ->assertSessionHasErrors('email');
        $this->get(route('admin.login'))->assertOk()->assertDontSee('not-a-credential');
        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    public function test_bcrypt_does_not_accept_a_long_password_by_silently_ignoring_its_suffix(): void
    {
        $password = str_repeat('a', 68).'Aa1!';
        $admin = User::factory()->create(['password' => $password]);
        $admin->forceFill(['is_admin' => true])->save();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => $password.'ignored-suffix'])
            ->assertSessionHasErrors(['password' => 'The password must be at most 72 bytes with the configured password hasher.']);
        $this->assertGuest();
    }

    public function test_nonadministrator_sessions_cannot_use_admin_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->get('/admin/reports')->assertForbidden();
        $this->post('/admin/logout')->assertForbidden();
    }

    public function test_login_is_rate_limited_and_never_creates_accounts(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.store'), ['email' => 'missing@example.test', 'password' => Str::random(20)])
                ->assertSessionHasErrors('email');
        }
        $this->post(route('admin.login.store'), ['email' => 'missing@example.test', 'password' => Str::random(20)])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['is_admin' => true])->assertNotFound();
    }

    public function test_logout_invalidates_the_session_and_admin_flag_is_guarded(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/admin')->assertOk();
        $sessionId = session()->getId();
        $token = session()->token();
        $this->post(route('admin.logout'))->assertRedirectToRoute('admin.login');
        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->get('/admin')->assertRedirectToRoute('admin.login');
        $this->assertFalse((new User)->isFillable('is_admin'));
        $this->assertArrayNotHasKey('is_admin', $admin->toArray());
    }
}
