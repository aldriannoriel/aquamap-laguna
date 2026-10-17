<?php

namespace Tests\Feature;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use Database\Seeders\WaterBodySeeder;
use Tests\TestCase;

class FishEvidenceMigrationTest extends TestCase
{
    public function test_forward_upgrade_preserves_legacy_records_and_adds_only_nullable_evidence(): void
    {
        $newMigration = 'database/migrations/2026_10_17_065926_add_evidence_metadata_to_water_body_fish_table.php';
        $oldMigrations = array_values(array_filter(
            glob('database/migrations/*.php'),
            fn (string $file): bool => $file !== $newMigration,
        ));
        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => $oldMigrations, '--no-interaction' => true])->assertExitCode(0);
        $this->seed(WaterBodySeeder::class);
        $fish = FishSpecies::forceCreate(['common_name' => 'Legacy fixture', 'scientific_name' => 'Fixture species', 'water_type' => 'fixture']);
        $link = WaterBodyFish::forceCreate(['water_body_id' => WaterBody::firstOrFail()->id, 'fish_species_id' => $fish->id])->fresh();
        $original = $link->getAttributes();

        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => [$newMigration], '--no-interaction' => true])->assertExitCode(0);

        $upgraded = $link->fresh();
        $this->assertSame($original, array_intersect_key($upgraded->getAttributes(), $original));
        $this->assertNull($upgraded->evidence_type);
        $this->assertNull($upgraded->sampling_period);
        $this->assertNull($upgraded->publication_year);
        $this->assertDatabaseCount('fish_species', 1);
        $this->assertDatabaseCount('water_body_fish', 1);
        $this->assertSame('insufficient_data', $upgraded->compatibility_status);
    }
}
