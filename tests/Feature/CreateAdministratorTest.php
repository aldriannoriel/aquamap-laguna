<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateAdministratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_explicit_interactive_setup_creates_a_new_admin_with_a_hashed_password(): void
    {
        $password = Str::random(24).'aA1!';
        $this->artisan('admin:create')->expectsQuestion('Administrator name', 'Isolated Admin')
            ->expectsQuestion('Administrator email', 'NEW-ADMIN@example.test')
            ->expectsQuestion('Password (12+ characters, mixed case, number and symbol)', $password)
            ->expectsQuestion('Confirm password', $password)->assertExitCode(0);
        $admin = User::sole();
        $this->assertTrue($admin->is_admin);
        $this->assertSame('new-admin@example.test', $admin->email);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertNotSame($password, $admin->password);
    }

    public function test_setup_never_promotes_or_changes_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.test']);
        $before = $user->fresh()->getAttributes();
        $this->artisan('admin:create')->expectsQuestion('Administrator name', 'Different name')
            ->expectsQuestion('Administrator email', 'EXISTING@example.test')->assertExitCode(1);
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_weak_or_mismatched_password_is_rejected(): void
    {
        $this->artisan('admin:create')->expectsQuestion('Administrator name', 'Isolated Admin')
            ->expectsQuestion('Administrator email', 'invalid@example.test')
            ->expectsQuestion('Password (12+ characters, mixed case, number and symbol)', 'x')
            ->expectsQuestion('Confirm password', 'y')->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unattended_setup_is_refused(): void
    {
        $this->artisan('admin:create', ['--no-interaction' => true])->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_setup_rejects_a_password_exceeding_bcrypt_byte_limit(): void
    {
        $password = str_repeat('a', 72).'Aa1!';
        $this->artisan('admin:create')->expectsQuestion('Administrator name', 'Isolated Admin')
            ->expectsQuestion('Administrator email', 'oversized@example.test')
            ->expectsQuestion('Password (12+ characters, mixed case, number and symbol)', $password)
            ->expectsQuestion('Confirm password', $password)->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_setup_refuses_an_unrelated_configured_database_without_connecting(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unrelated_test_target']);
        $this->artisan('admin:create')->assertExitCode(1);
        config(['database.default' => 'sqlite']);
        $this->assertDatabaseCount('users', 0);
    }
}
