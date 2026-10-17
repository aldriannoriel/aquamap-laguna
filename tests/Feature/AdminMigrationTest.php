<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\User;
use App\Models\WaterBody;
use Database\Seeders\WaterBodySeeder;
use Tests\TestCase;

class AdminMigrationTest extends TestCase
{
    public function test_forward_admin_upgrade_preserves_existing_rows_and_grants_no_privileges(): void
    {
        $new = ['database/migrations/2026_10_17_075301_add_is_admin_to_users_table.php', 'database/migrations/2026_10_17_075302_create_report_reviews_table.php'];
        $old = array_values(array_diff(glob('database/migrations/*.php'), $new));
        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => $old, '--no-interaction' => true])->assertExitCode(0);
        $this->seed(WaterBodySeeder::class);
        $user = User::factory()->create()->fresh();
        $report = PollutionReport::forceCreate(['water_body_id' => WaterBody::firstOrFail()->id, 'pollution_type' => 'other', 'description' => 'Legacy isolated fixture.'])->fresh();
        $originalUser = $user->getAttributes();
        $originalReport = $report->getAttributes();
        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => $new, '--no-interaction' => true])->assertExitCode(0);
        $this->assertSame($originalUser, array_intersect_key($user->fresh()->getAttributes(), $originalUser));
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame($originalReport, $report->fresh()->getAttributes());
        $this->assertDatabaseCount('report_reviews', 0);
    }
}
