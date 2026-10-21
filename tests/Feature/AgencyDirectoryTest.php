<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Referral;
use App\Models\User;
use App\Models\WaterBody;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgencyDirectoryTest extends TestCase
{
    public function test_malformed_directory_input_returns_readable_validation_instead_of_breaking_the_retry_form(): void
    {
        $this->actingAs($this->admin())->from(route('admin.agencies.create'))->post(route('admin.agencies.store'), [
            'name' => ['invalid'], 'municipality' => ['invalid'], 'email' => ['invalid'], 'active' => ['invalid'],
            'notes' => ['invalid'], 'water_body_ids' => 'invalid',
        ])->assertRedirectToRoute('admin.agencies.create')->assertSessionHasErrors(['name', 'municipality', 'email', 'active', 'water_body_ids']);
        $this->get(route('admin.agencies.create'))->assertOk();
        $this->assertDatabaseCount('agencies', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    private function data(): array
    {
        return ['name' => 'Provisional recipient', 'municipality' => 'San Pablo City', 'email' => Referral::TEST_RECIPIENT,
            'active' => '1', 'verification_status' => 'provisional', 'water_body_ids' => WaterBody::supported()->where('municipality', 'San Pablo City')->limit(2)->pluck('id')->all()];
    }

    public function test_directory_requires_admin_and_all_initial_routes_are_provisional_test_addresses(): void
    {
        $this->get(route('admin.agencies.index'))->assertRedirectToRoute('admin.login');
        $this->actingAs(User::factory()->create())->get(route('admin.agencies.index'))->assertForbidden();
        $before = WaterBody::all()->toArray();
        $this->artisan('agencies:initialize', ['--no-interaction' => true])->assertExitCode(0);
        $this->assertGreaterThan(1, Agency::count());
        foreach (Agency::with('waterBodies')->get() as $agency) {
            $this->assertSame(Referral::TEST_RECIPIENT, $agency->email);
            $this->assertSame('provisional', $agency->verification_status);
            $this->assertNotEmpty($agency->waterBodies);
            foreach ($agency->waterBodies as $water) {
                $this->assertSame($agency->municipality, $water->municipality);
                $this->assertContains($water->category, ['lake', 'river', 'waterfall']);
            }
        }
        $first = Agency::all()->toArray();
        $this->artisan('agencies:initialize', ['--no-interaction' => true])->assertExitCode(0);
        $this->assertSame($first, Agency::all()->toArray());
        $this->assertSame($before, WaterBody::all()->toArray());
        $this->assertDatabaseCount('referrals', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_admin_crud_duplicate_detection_search_assignment_and_deletion_confirmation(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.agencies.store'), $this->data())->assertSessionHasNoErrors()->assertRedirectToRoute('admin.agencies.index');
        $a = Agency::sole();
        $this->assertCount(2, $a->waterBodies);
        $this->post(route('admin.agencies.store'), [...$this->data(), 'name' => '  provisional recipient  '])->assertSessionHasErrors('identity_key');
        $this->put(route('admin.agencies.update', $a), [...$this->data(), 'email' => 'new@example.test', 'active' => '0'])->assertSessionHasNoErrors();
        $this->get(route('admin.agencies.index', ['q' => 'new@example.test', 'active' => '0']))->assertOk()->assertSee('new@example.test');
        $this->delete(route('admin.agencies.destroy', $a))->assertSessionHasErrors('confirm_delete');
        $this->assertDatabaseCount('agencies', 1);
        $this->delete(route('admin.agencies.destroy', $a), ['confirm_delete' => '1'])->assertRedirectToRoute('admin.agencies.index');
        $this->assertDatabaseCount('agencies', 0);
        $this->assertDatabaseCount('agency_water_body', 0);
    }

    public function test_deleted_initial_routes_stay_deleted_and_legacy_bodies_cannot_be_assigned(): void
    {
        $this->artisan('agencies:initialize', ['--no-interaction' => true])->assertExitCode(0);
        $count = Agency::count();
        $a = Agency::firstOrFail();
        $this->actingAs($this->admin())->delete(route('admin.agencies.destroy', $a), ['confirm_delete' => '1'])->assertRedirect();
        $this->artisan('agencies:initialize', ['--no-interaction' => true])->assertExitCode(0);
        $this->assertSame($count - 1, Agency::count());
        $id = DB::table('water_bodies')->insertGetId(['name' => 'Legacy resort', 'municipality' => 'Legacy', 'water_type' => 'Unknown', 'category' => 'spring', 'site_type' => 'resort']);
        $this->post(route('admin.agencies.store'), [...$this->data(), 'water_body_ids' => [$id]])->assertSessionHasErrors('water_body_ids.0');
    }
}
