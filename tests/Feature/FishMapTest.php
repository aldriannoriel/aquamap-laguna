<?php

namespace Tests\Feature;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WaterBodySeeder;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FishMapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_map_presents_historical_occurrence_separately_from_insufficient_compatibility_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertSee('Nile tilapia')->assertSee('Oreochromis niloticus')
            ->assertSee('Documented occurrence')->assertSee('Historical aquaculture')
            ->assertSee('July-September 2017')->assertSee('Publication year: 2019.')
            ->assertSee('Potential habitat compatibility')->assertSee('Insufficient data')
            ->assertSee('not a wild-population record')->assertSee('Species habitat reference');
        $data = $response->viewData('fishByLake');
        foreach ($data as $fish) {
            $this->assertCount(1, $fish);
            $this->assertSame([
                'id', 'common_name', 'scientific_name', 'water_type', 'habitat_requirements', 'source_url',
                'occurrence_source_url', 'evidence_type', 'sampling_period', 'publication_year', 'compatibility_status',
            ], array_keys($fish[0]));
            $this->assertSame('insufficient_data', $fish[0]['compatibility_status']);
        }
    }

    public function test_lake_records_remain_scoped_and_unvalidated_statuses_are_not_published_as_suitability(): void
    {
        $this->seed(DatabaseSeeder::class);
        $sampaloc = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $yambo = WaterBody::where('name', 'Yambo Lake')->sole();
        $fixture = FishSpecies::forceCreate(['common_name' => 'Yambo-only fixture', 'scientific_name' => 'Fixture species', 'water_type' => 'fixture']);
        WaterBodyFish::forceCreate(['water_body_id' => $yambo->id, 'fish_species_id' => $fixture->id, 'compatibility_status' => 'compatible']);

        $response = $this->get(route('map.index'));

        $response->assertOk();
        $fish = $response->viewData('fishByLake');
        $this->assertCount(1, $fish[$sampaloc->id]);
        $this->assertCount(2, $fish[$yambo->id]);
        $this->assertSame('Yambo-only fixture', $fish[$yambo->id][1]['common_name']);
        $this->assertSame('insufficient_data', $fish[$yambo->id][1]['compatibility_status']);
        $this->assertNull($fish[$yambo->id][1]['evidence_type']);
    }

    #[TestWith([null])]
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['data:text/html,<script>alert(1)</script>'])]
    #[TestWith(['ftp://example.test/fixture'])]
    public function test_missing_or_unsafe_sources_and_habitat_details_have_honest_fallbacks(?string $source): void
    {
        $this->seed(DatabaseSeeder::class);
        FishSpecies::query()->update(['source_url' => $source, 'habitat_requirements' => '   ']);
        WaterBodyFish::query()->update(['source_url' => $source]);

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertSee('Habitat requirements are not available for this species.')
            ->assertSee('No species habitat reference is available.')
            ->assertSee('No occurrence research source is available.')->assertSee('Evidence incomplete')
            ->assertDontSee('Historical aquaculture');
        foreach ($response->viewData('fishByLake') as $fish) {
            $this->assertNull($fish[0]['source_url']);
            $this->assertNull($fish[0]['occurrence_source_url']);
            $this->assertNull($fish[0]['habitat_requirements']);
        }
    }

    public function test_legacy_link_metadata_does_not_become_a_verified_occurrence(): void
    {
        $this->seed(DatabaseSeeder::class);
        WaterBodyFish::query()->update(['evidence_type' => null, 'sampling_period' => null, 'publication_year' => null]);

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertSee('Water-body-specific occurrence evidence is incomplete.')
            ->assertDontSee('Historical aquaculture')->assertSee('Insufficient data');
    }

    public function test_fish_text_is_escaped_without_exposing_private_model_metadata(): void
    {
        $this->seed(DatabaseSeeder::class);
        $malicious = '<img src=x onerror=alert(1)>';
        FishSpecies::query()->update(['common_name' => $malicious, 'scientific_name' => $malicious, 'habitat_requirements' => $malicious]);
        WaterBodyFish::query()->update(['sampling_period' => $malicious, 'explanation' => 'Private assessment fixture']);

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertSee($malicious)->assertDontSee($malicious, false)
            ->assertDontSee('Private assessment fixture');
    }

    public function test_lakes_without_fish_links_do_not_imply_absence_or_suitability(): void
    {
        $this->seed(WaterBodySeeder::class);

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertSee('No verified fish records are available for this water body.')
            ->assertSee('This does not establish that fish are absent.');
        foreach ($response->viewData('fishByLake') as $fish) {
            $this->assertSame([], $fish);
        }
    }
}
