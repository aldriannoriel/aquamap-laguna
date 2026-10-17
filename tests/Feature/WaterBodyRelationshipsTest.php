<?php

namespace Tests\Feature;

use App\Models\FishSpecies;
use App\Models\PollutionReport;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class WaterBodyRelationshipsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    /**
     * All fish and reports in this class are synthetic, isolated test fixtures.
     *
     * @return array{WaterBody, FishSpecies, WaterBodyFish, PollutionReport}
     */
    private function relatedRecords(): array
    {
        $this->seed(WaterBodySeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $fish = FishSpecies::forceCreate([
            'common_name' => 'Test fixture only',
            'scientific_name' => 'Not a scientific identification',
            'water_type' => 'fixture',
        ]);
        $link = WaterBodyFish::forceCreate([
            'water_body_id' => $lake->id,
            'fish_species_id' => $fish->id,
        ]);
        $report = PollutionReport::forceCreate([
            'water_body_id' => $lake->id,
            'pollution_type' => 'test_fixture',
            'description' => 'Synthetic test fixture; not a real pollution report.',
        ]);

        return [$lake, $fish, $link->fresh(), $report->fresh()];
    }

    public function test_relationships_resolve_both_directions_and_preserve_insufficient_data(): void
    {
        [$lake, $fish, $link, $report] = $this->relatedRecords();

        $this->assertTrue($link->waterBody->is($lake));
        $this->assertTrue($link->fishSpecies->is($fish));
        $this->assertTrue($lake->waterBodyFish->sole()->is($link));
        $this->assertTrue($fish->waterBodyFish->sole()->is($link));
        $this->assertTrue($lake->fishSpecies->sole()->is($fish));
        $this->assertTrue($fish->waterBodies->sole()->is($lake));
        $this->assertSame('insufficient_data', $lake->fishSpecies->sole()->pivot->compatibility_status);
        $this->assertNull($link->explanation);
        $this->assertNull($link->source_url);
        $this->assertNotNull($lake->fishSpecies->sole()->pivot->created_at);
        $this->assertTrue($report->waterBody->is($lake));
        $this->assertTrue($lake->pollutionReports->sole()->is($report));
        $this->assertSame('pending', $report->status);
    }

    public function test_rejects_duplicate_lake_species_pairs(): void
    {
        [$lake, $fish] = $this->relatedRecords();

        $this->expectException(QueryException::class);

        WaterBodyFish::forceCreate(['water_body_id' => $lake->id, 'fish_species_id' => $fish->id]);
    }

    public function test_rejects_orphan_pollution_reports(): void
    {
        $this->expectException(QueryException::class);

        PollutionReport::forceCreate([
            'water_body_id' => 999,
            'pollution_type' => 'test_fixture',
            'description' => 'Synthetic orphan fixture.',
        ]);
    }

    public function test_rejects_orphan_lake_species_links(): void
    {
        $this->expectException(QueryException::class);

        WaterBodyFish::forceCreate(['water_body_id' => 999, 'fish_species_id' => 999]);
    }

    public function test_deleting_a_lake_removes_dependents_and_preserves_species(): void
    {
        [$lake, $fish, $link, $report] = $this->relatedRecords();

        $lake->delete();

        $this->assertModelMissing($lake);
        $this->assertModelMissing($link);
        $this->assertModelMissing($report);
        $this->assertModelExists($fish);
        $this->assertDatabaseCount('water_bodies', 2);
    }

    public function test_deleting_a_species_removes_its_links_and_preserves_lakes_and_reports(): void
    {
        [$lake, $fish, $link, $report] = $this->relatedRecords();

        $fish->delete();

        $this->assertModelMissing($fish);
        $this->assertModelMissing($link);
        $this->assertModelExists($lake);
        $this->assertModelExists($report);
    }
}
