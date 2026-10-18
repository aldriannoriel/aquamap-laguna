<?php

namespace Tests\Feature;

use App\Models\WaterBody;
use App\Support\ExplorerProfile;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class WaterBodyCardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_complete_profile_dialog_and_all_verified_candidate_categories_render_with_safe_photos(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $response = $this->get(route('map.index'))->assertOk()->assertSee('id="water-detail-dialog"', false)
            ->assertSee('Close profile')->assertSee('Scientific water-quality assessment: Not Assessed.')
            ->assertSee('Historical aquaculture')->assertSee('Potential habitat compatibility')->assertSee('Insufficient data')
            ->assertSee('Research &amp; sources', false)->assertSee('Not Assessed')->assertSee('Spring type:')
            ->assertSee('Barrera marquez')->assertSee('Dionisia.besas')->assertSee('Permission');
        $this->assertDatabaseCount('water_bodies', 28);
        $this->assertCount(28, $response->viewData('lakes'));
        $images = array_filter(array_column($response->viewData('profiles'), 'image'));
        $this->assertCount(8, $images);
        foreach ($images as $image) {
            $this->assertFileExists(public_path($image['path']));
            $this->assertSame('image/jpeg', getimagesize(public_path($image['path']))['mime']);
            $this->assertNotEmpty($image['author']);
            $this->assertNotEmpty($image['license']);
            $this->assertNotNull(ExplorerProfile::url($image['source_url']));
            $this->assertNotNull(ExplorerProfile::url($image['license_url']));
        }
        foreach (['Dolcezza Hotspring Resort', 'Tirta Spring Villa', 'Stellaluna Private Resort'] as $name) {
            $this->assertDatabaseMissing('water_bodies', ['name' => $name]);
        }
    }

    public function test_sidebar_counts_exclude_unverified_geometry_while_research_profiles_remain_available(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        WaterBody::where('name', 'Sampaloc Lake')->update(['geography' => null]);
        $response = $this->get(route('map.index'))->assertOk()->assertSee('Sampaloc Lake')->assertSee('Location awaiting verification');
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        foreach (['all' => '27', 'lake' => '9', 'river' => '5', 'waterfall' => '11', 'spring' => '2'] as $category => $count) {
            $this->assertSame($count, trim($xpath->evaluate('string(//button[@data-category="'.$category.'"]//span[@data-category-count])')));
        }
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $this->assertSame(1, $xpath->query('//li[@data-water-row="'.$lake->id.'"][@hidden]')->length);
    }

    public function test_profile_sections_keep_classification_fish_reports_and_scientific_evidence_distinct(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();

        $response = $this->get(route('map.index'))->assertOk();

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $root = '//article[@id="profile-'.$lake->id.'"]';
        foreach ([
            'overview' => ['Sampaloc Lake', 'San Pablo City', 'Judgefloro'],
            'classification' => ['freshwater', 'drinking-water safety'],
            'fish' => ['Nile tilapia', 'Oreochromis niloticus', 'July-September 2017', 'Insufficient data'],
            'pollution' => ['Community Pollution Reports', '0 currently verified community reports.', 'Scientific Water Quality Assessment', 'Not Assessed', 'No published measurement values'],
            'research' => ['Research & sources', 'OpenStreetMap contributors', 'Geographic reference and limitations', 'Image attribution', 'Judgefloro'],
        ] as $section => $expectedText) {
            $panels = $xpath->query($root.'//section[@data-profile-section="'.$section.'"]');
            $this->assertSame(1, $panels->length);
            $this->assertFalse($panels->item(0)->hasAttribute('hidden'), 'Non-JavaScript research profiles retain every section.');
            foreach ($expectedText as $text) {
                $this->assertStringContainsString($text, $panels->item(0)->textContent);
            }
        }
        $this->assertSame(5, $xpath->query('//div[@id="profile-tabs"]/button[@role="tab"]')->length);
        $this->assertSame(1, $xpath->query('//button[@data-profile-tab="overview"][@aria-selected="true"][@tabindex="0"]')->length);
        $this->assertGreaterThan(0, $xpath->query($root.'//section[@data-profile-section="research"]//article[@data-research-card]')->length);
        $this->assertSame(2, $xpath->query($root.'//section[@data-profile-section="pollution"]/section')->length);
    }

    public function test_new_geometries_preserve_island_holes_and_existing_lake_coordinates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $original = WaterBody::orderBy('id')->get()->map(fn (WaterBody $lake): array => $lake->only(['id', 'name', 'latitude', 'longitude', 'description', 'source_url']))->all();
        $this->seed(ExplorerSeeder::class);
        $this->assertSame($original, WaterBody::orderBy('id')->limit(3)->get()->map(fn (WaterBody $lake): array => $lake->only(['id', 'name', 'latitude', 'longitude', 'description', 'source_url']))->all());
        foreach (['Lumot Lake', 'Caliraya Lake', 'Laguna de Bay'] as $name) {
            $lake = WaterBody::where('name', $name)->sole();
            $geometry = ExplorerProfile::geography($lake->geography)['geometry'];
            $this->assertSame('Polygon', $geometry['type']);
            $this->assertGreaterThan(1, count($geometry['coordinates']), 'Source islands must remain holes.');
            foreach ($geometry['coordinates'] as $ring) {
                $this->assertSame($ring[0], $ring[array_key_last($ring)]);
            }
        }
        $point = WaterBody::where('name', 'Hidden Valley Springs')->sole();
        $this->assertSame('Point', $point->geography['geometry']['type']);
        $this->assertSame('resort', $point->site_type);
        $this->assertSame('natural_spring', $point->spring_type);
        $this->assertStringContainsString('2014 OSM node', $point->geography['note']);
        $this->assertDatabaseCount('fish_species', 1);
        $this->assertDatabaseCount('water_body_fish', 3);
        $this->assertDatabaseCount('pollution_reports', 0);
    }
}
