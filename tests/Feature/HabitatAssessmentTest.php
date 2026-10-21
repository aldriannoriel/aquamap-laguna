<?php

namespace Tests\Feature;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use App\Support\ExplorerProfile;
use App\Support\HabitatCatalogue;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HabitatAssessmentTest extends TestCase
{
    private const FIRST_BATCH = ['Bunot Lake', 'Calibato Lake', 'Caliraya Lake', 'Laguna de Bay'];

    private function fixture(): void
    {
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);
        $this->artisan('lakes:import', ['--no-interaction' => true])->assertExitCode(0);
        $this->assertSame(53, WaterBody::supported()->count());
        Mail::fake();
        Queue::fake();
    }

    private function snapshot(): array
    {
        return [WaterBody::all()->map->getAttributes()->all(), FishSpecies::all()->map->getAttributes()->all(), WaterBodyFish::all()->map->getAttributes()->all()];
    }

    public function test_batch_preview_is_read_only_and_apply_is_additive_and_idempotent(): void
    {
        $this->fixture();
        $before = $this->snapshot();
        $preview = HabitatCatalogue::import(self::FIRST_BATCH, false);
        $this->assertSame(7, $preview['species_created']);
        $this->assertSame(24, $preview['links_created']);
        $this->assertSame(2, $preview['water_types_updated']);
        $this->assertSame($before, $this->snapshot());
        $this->artisan('habitat:import', ['names' => self::FIRST_BATCH, '--apply' => true])->assertExitCode(0);
        $this->assertDatabaseCount('fish_species', 8);
        $this->assertDatabaseCount('water_body_fish', 27);
        $this->assertSame($before[1][0], FishSpecies::first()->getAttributes());
        $this->assertSame($before[2], WaterBodyFish::where('evidence_type', 'aquaculture')->get()->map->getAttributes()->all());
        foreach ($before[0] as $attributes) {
            $after = WaterBody::findOrFail($attributes['id'])->getAttributes();
            foreach (array_diff(array_keys($attributes), ['water_type', 'updated_at']) as $key) {
                $this->assertSame($attributes[$key], $after[$key], $key.' must be preserved.');
            }
        }
        $applied = $this->snapshot();
        $repeat = HabitatCatalogue::import(self::FIRST_BATCH, true);
        $this->assertSame([0, 0, 0], [$repeat['species_created'], $repeat['links_created'], $repeat['water_types_updated']]);
        $this->assertSame($applied, $this->snapshot());
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_classification_conflicts_and_inventory_discrepancies_roll_back_the_whole_batch(): void
    {
        $this->fixture();
        $lake = WaterBody::where('name', 'Laguna de Bay')->sole();
        $lake->forceFill(['water_type' => 'brackish'])->save();
        $before = $this->snapshot();
        try {
            HabitatCatalogue::import(self::FIRST_BATCH, true);
            $this->fail('An existing classification must not be overwritten.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('classification', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        $lake->forceFill(['water_type' => 'Not assessed', 'municipality' => 'Unverified fixture locality'])->save();
        $before = $this->snapshot();
        $this->artisan('habitat:import', ['names' => self::FIRST_BATCH, '--apply' => true])->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_duplicate_species_and_oversized_or_repeated_batches_are_refused_without_writes(): void
    {
        $this->fixture();
        FishSpecies::forceCreate(['scientific_name' => 'OREOCHROMIS NILOTICUS', 'common_name' => 'Duplicate fixture', 'water_type' => 'freshwater']);
        $before = $this->snapshot();
        foreach ([self::FIRST_BATCH, ['Bunot Lake'], ['Bunot Lake', 'Bunot Lake', 'Laguna de Bay'],
            ['Bunot Lake', 'Calibato Lake', 'Caliraya Lake', 'Laguna de Bay', 'Pandin Lake', 'Yambo Lake']] as $names) {
            $this->artisan('habitat:import', ['names' => $names, '--apply' => true])->assertExitCode(1);
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_every_authored_assessment_projects_real_ids_explanations_and_sources_separately_from_occurrences(): void
    {
        $this->fixture();
        $documented = WaterBodyFish::all()->map->getAttributes()->all();
        $data = HabitatCatalogue::load();
        $remaining = array_keys($data['water_bodies']);
        while ($remaining !== []) {
            $names = array_splice($remaining, 0, count($remaining) > 5 ? 4 : count($remaining));
            HabitatCatalogue::import($names, true);
        }
        $response = $this->get(route('map.index'))->assertOk()->assertSee('Possible Fish')->assertSee('Why these fish?')
            ->assertSee('Documented Fish Records')->assertSee('Their presence has not been confirmed.')
            ->assertSee('July-September 2017')->assertSee('Historical aquaculture')->assertSee('Photo unavailable');
        $profiles = $response->viewData('profiles');
        foreach (['Bunot Lake' => 5, 'Laguna de Bay' => 8] as $name => $count) {
            $water = WaterBody::where('name', $name)->sole();
            $this->assertCount($count, $profiles[$water->id]['possible_fish']['candidates']);
        }
        foreach ($data['water_bodies'] as $name => $entry) {
            $water = WaterBody::where('name', $name)->sole();
            $assessment = $profiles[$water->id]['possible_fish'];
            $this->assertTrue($assessment['complete'], $name);
            foreach ($assessment['candidates'] as $candidate) {
                $this->assertSame($water->id, $candidate['water_body_id']);
                $this->assertSame('preliminary_habitat_estimate', $candidate['evidence_classification']);
                $this->assertNotEmpty($candidate['explanation']);
                $this->assertNotEmpty($candidate['habitat_requirements']);
                $this->assertNotEmpty($candidate['limitations']);
                $this->assertNotEmpty($candidate['references']);
                $this->assertDatabaseHas('water_body_fish', ['water_body_id' => $water->id, 'fish_species_id' => $candidate['id']]);
            }
        }
        $this->assertSame(3, array_sum(array_map(count(...), $response->viewData('fishByLake'))));
        foreach ($response->viewData('fishByLake') as $records) {
            $this->assertTrue(array_is_list($records));
        }
        $this->assertSame($documented, WaterBodyFish::where('evidence_type', 'aquaculture')->get()->map->getAttributes()->all());
        $this->assertSame(0, WaterBodyFish::where('evidence_type', 'potential_habitat')->whereNotNull('sampling_period')->count());
        $response->assertDontSee('data-compatibility-status="compatible"', false);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_geographic_types_need_explicit_scope_limitations_and_exact_identity_and_cannot_verify_fish_names(): void
    {
        $entry = ['status' => 'geographic_assessment', 'type' => 'freshwater', 'municipality' => 'Fixture locality', 'category' => 'river',
            'source_url' => 'https://example.test/geography', 'reference' => 'Geography fixture', 'note' => 'General inland assessment.',
            'basis' => 'general_geographic_assessment', 'scope' => 'Inland freshwater reaches only', 'limitations' => 'Salinity is unmeasured.'];
        $this->assertSame('general_geographic_assessment', ExplorerProfile::waterType($entry, 'Fixture locality', 'river')['basis']);
        foreach (['scope', 'limitations', 'source_url', 'basis'] as $field) {
            $invalid = $entry;
            unset($invalid[$field]);
            $this->assertNull(ExplorerProfile::waterType($invalid, 'Fixture locality', 'river'));
        }
        $this->assertNull(ExplorerProfile::waterType($entry, 'Other locality', 'river'));
        $names = json_decode(file_get_contents(database_path('data/fish-name-evidence.json')), true, flags: JSON_THROW_ON_ERROR);
        $name = $names['Oreochromis niloticus'];
        $name['status'] = 'geographic_assessment';
        $this->assertNull(ExplorerProfile::fishName($name, 'Oreochromis niloticus'));
    }

    public function test_checkpoint_is_atomically_replaced_with_actual_coverage_without_database_changes(): void
    {
        $this->fixture();
        $before = $this->snapshot();
        $saved = null;
        File::shouldReceive('replace')->once()->withArgs(function (string $path, string $text) use (&$saved): bool {
            $saved = $text;

            return $path === base_path('docs/FISH_HABITAT_CHECKPOINT.md');
        });
        $this->artisan('habitat:checkpoint', ['--next' => 'Run the next verified test.'])->assertExitCode(0);
        $this->assertStringContainsString('possible fish coverage: 0/53', $saved);
        $this->assertStringContainsString('Bunot Lake', $saved);
        $this->assertStringContainsString('Run the next verified test.', $saved);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_maintenance_refuses_non_memory_sqlite_before_opening_any_file(): void
    {
        $original = config('database.connections.sqlite.database');
        $path = storage_path('app/fish-habitat/must-not-create.sqlite');
        config(['database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');
        try {
            $this->expectException(\RuntimeException::class);
            HabitatCatalogue::guardedConnection();
        } finally {
            $this->assertFileDoesNotExist($path);
            config(['database.connections.sqlite.database' => $original]);
            DB::purge('sqlite');
        }
    }
}
