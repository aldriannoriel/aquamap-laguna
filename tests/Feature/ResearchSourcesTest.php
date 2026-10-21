<?php

namespace Tests\Feature;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use App\Support\ResearchSources;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Tests\TestCase;

class ResearchSourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_all_52_profiles_keep_six_groups_and_unique_documents_without_changing_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);
        $before = [WaterBody::all()->toArray(), FishSpecies::all()->toArray(), WaterBodyFish::all()->toArray()];

        $response = $this->get(route('map.index'))->assertOk();

        $this->assertCount(52, $response->viewData('profiles'));
        $uses = 0;
        foreach ($response->viewData('profiles') as $profile) {
            $library = $profile['research'];
            $this->assertSame(array_keys(ResearchSources::GROUPS), array_keys($library['groups']));
            $keys = array_map(ResearchSources::key(...), array_column($library['citations'], 'url'));
            $this->assertSame(count($keys), count(array_unique($keys)));
            $this->assertSame([], $library['excluded']);
            foreach ($library['citations'] as $citation) {
                $this->assertNotEmpty($citation['claims']);
            }
            $uses += $library['source_uses'];
        }
        $this->assertSame(541, $uses);
        $this->assertSame($before, [WaterBody::all()->toArray(), FishSpecies::all()->toArray(), WaterBodyFish::all()->toArray()]);
        $response->assertSee('OpenStreetMap contributors, ODbL 1.0')->assertSee('Image attribution')
            ->assertSee('Plapla')->assertSee('Pagpapalaki ng tilapya')->assertSee('Naming evidence only');
    }

    public function test_one_document_keeps_measurement_hydrology_and_table_claims(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);

        $response = $this->get(route('map.index'))->assertOk();

        $lake = collect($response->viewData('lakes'))->firstWhere('name', 'Yambo Lake');
        $library = $response->viewData('profiles')[$lake['id']]['research'];
        $source = $library['citations']['https://www.frontiersin.org/journals/microbiology/articles/10.3389/fmicb.2024.1410666/full'];
        $this->assertSame(['measurements', 'hydrology'], array_keys($source['claims']));
        $this->assertSame('2024', $source['year']);
        $this->assertStringContainsString('Bicaldo', $source['publisher']);
        $this->assertStringContainsString('#T1', $source['url']);
        $this->assertSame(2, count($source['claims']['measurements']) + count($source['claims']['hydrology']));
        $this->assertGreaterThan(0, $library['duplicates_removed']);
    }

    public function test_missing_metadata_stays_visible_and_unsafe_references_are_excluded(): void
    {
        $this->seed(DatabaseSeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $lake->forceFill(['research_references' => [['url' => 'https://example.test/unknown'], ['url' => 'javascript:alert(1)', 'title' => 'Unsafe fixture', 'publisher' => 'Fixture']]])->save();

        $response = $this->get(route('map.index'))->assertOk();

        $source = $response->viewData('profiles')[$lake->id]['research']['citations']['https://example.test/unknown'];
        $this->assertNull($source['title']);
        $this->assertNull($source['publisher']);
        $this->assertNull($source['year']);
        $response->assertSee('Source title not provided')->assertSee('Author / organization: Not provided.')
            ->assertSee('Publication year: Not provided.')->assertDontSee('Unsafe fixture');
    }

    public function test_wrong_water_body_scope_is_not_presented_as_supporting_evidence(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $river = WaterBody::where('name', 'Balanac River')->sole();
        $url = 'https://www.frontiersin.org/journals/microbiology/articles/10.3389/fmicb.2024.1410666/full';
        $river->forceFill(['research_references' => [['title' => 'Wrong lake study fixture', 'publisher' => 'Fixture', 'url' => $url]]])->save();

        $response = $this->get(route('map.index'))->assertOk();

        $library = $response->viewData('profiles')[$river->id]['research'];
        $this->assertArrayNotHasKey($url, $library['citations']);
        $this->assertArrayHasKey($url, $library['excluded']);
        $this->assertSame($url, $river->fresh()->research_references[0]['url']);
    }

    public function test_deduplication_preserves_query_versions_and_additional_fragment_locations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $response = $this->get(route('map.index'))->assertOk();
        $lake = $response->viewData('lakes')[0];
        $profile = $response->viewData('profiles')[$lake['id']];
        $profile['references'] = [
            ['url' => 'https://example.test/study?version=1#table1', 'title' => 'Study', 'publisher' => 'Fixture'],
            ['url' => 'https://example.test/study?version=1#table2', 'title' => 'Study', 'publisher' => 'Fixture'],
            ['url' => 'https://example.test/study?version=2', 'title' => 'Revised study', 'publisher' => 'Fixture'],
        ];

        $library = ResearchSources::build($lake, $profile, []);

        $this->assertCount(2, $library['citations']['https://example.test/study?version=1']['locations']);
        $this->assertArrayHasKey('https://example.test/study?version=2', $library['citations']);
        $this->assertSame('https://example.test/study?value=a/', ResearchSources::key('https://example.test/study?value=a/#table'));
    }

    public function test_map_revisions_are_retained_without_inventing_a_publication_year(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);

        $response = $this->get(route('map.index'))->assertOk();

        $lake = collect($response->viewData('lakes'))->firstWhere('name', 'Aliw Falls');
        $source = $response->viewData('profiles')[$lake['id']]['research']['citations'][$lake['source_url']];
        $this->assertNull($source['year']);
        $this->assertSame(['Map revision 2023-05-08 (v4)'], $source['contexts']);
    }

    public function test_citation_titles_authors_and_claims_are_escaped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $malicious = '<img src=x onerror=alert(1)>';
        WaterBody::where('name', 'Sampaloc Lake')->update(['research_references' => json_encode([['url' => 'https://example.test/escaped', 'title' => $malicious, 'publisher' => $malicious]])]);

        $response = $this->get(route('map.index'))->assertOk();

        $response->assertSee($malicious)->assertDontSee($malicious, false);
    }
}
