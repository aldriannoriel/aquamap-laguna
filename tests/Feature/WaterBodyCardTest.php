<?php

namespace Tests\Feature;

use App\Models\WaterBody;
use App\Support\ExplorerProfile;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\TestWith;
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
            ->assertSee('Close profile')->assertDontSee('Visible Pollution Reports')
            ->assertSee('Historical aquaculture')->assertSee('Potential habitat compatibility')->assertSee('Insufficient data')
            ->assertSee('Research &amp; Sources', false)->assertSee('General geographic assessment')->assertDontSee('Spring type:')
            ->assertSee('Barrera marquez')->assertSee('Dionisia.besas')->assertSee('Photo by');
        $this->assertDatabaseCount('water_bodies', 28);
        $this->assertCount(28, $response->viewData('lakes'));
        $images = array_filter(array_column($response->viewData('profiles'), 'image'));
        $this->assertCount(26, $images);
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

    public function test_photo_supplement_preserves_existing_metadata_and_never_changes_stored_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $before = WaterBody::orderBy('id')->get()->toArray();
        $response = $this->get(route('map.index'))->assertOk();
        $profiles = $response->viewData('profiles');
        $supplement = json_decode(file_get_contents(database_path('data/explorer-images.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $supplement);
        $replacements = json_decode(file_get_contents(database_path('data/waterfall-photos.json')), true, flags: JSON_THROW_ON_ERROR);
        $originals = 0;
        foreach (WaterBody::whereIn('category', ['lake', 'river', 'waterfall'])->get() as $lake) {
            if (isset($replacements[$lake->name])) {
                $expected = $replacements[$lake->name]['image'];
                $expected['license_url'] = url($expected['license_url']);
                $this->assertSame($expected, $profiles[$lake->id]['image']);

                continue;
            }
            if ($lake->image !== null) {
                $this->assertSame($lake->image, $profiles[$lake->id]['image']);
                $originals++;
            } elseif (isset($supplement[$lake->name])) {
                $this->assertSame($supplement[$lake->name]['image'], $profiles[$lake->id]['image']);
                $size = getimagesize(public_path($profiles[$lake->id]['image']['path']));
                $this->assertLessThanOrEqual(960, max($size[0], $size[1]));
                $this->assertLessThan(250000, filesize(public_path($profiles[$lake->id]['image']['path'])));
            } else {
                $this->assertNull($profiles[$lake->id]['image']);
            }
        }
        $this->assertSame(8, $originals);
        $this->assertSame($before, WaterBody::orderBy('id')->get()->toArray());
    }

    public function test_supplement_requires_exact_profile_identity_and_existing_image_takes_precedence(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $lake = WaterBody::where('name', 'Mohicap Lake')->sole();
        $original = WaterBody::where('name', 'Sampaloc Lake')->sole()->image;
        $lake->forceFill(['image' => $original])->save();
        $this->assertSame($original, $this->get(route('map.index'))->viewData('profiles')[$lake->id]['image']);

        $lake->forceFill(['image' => ['path' => '/images/water-bodies/missing-file.jpg']])->save();
        $this->assertNull($this->get(route('map.index'))->viewData('profiles')[$lake->id]['image']);

        $lake->forceFill(['image' => null, 'municipality' => 'Different locality'])->save();
        $this->assertNull($this->get(route('map.index'))->viewData('profiles')[$lake->id]['image']);

        $lake->update(['municipality' => 'San Pablo City', 'name' => 'Different lake']);
        $this->assertNull($this->get(route('map.index'))->viewData('profiles')[$lake->id]['image']);
    }

    public function test_portrait_presentation_is_allowlisted_and_missing_assets_remain_placeholders(): void
    {
        $image = json_decode(file_get_contents(database_path('data/explorer-images.json')), true, flags: JSON_THROW_ON_ERROR)['Hulugan Falls']['image'];
        $this->assertSame('contain', ExplorerProfile::image($image)['fit']);
        $this->assertArrayNotHasKey('fit', ExplorerProfile::image([...$image, 'fit' => 'arbitrary-css']));
        $this->assertNull(ExplorerProfile::image([...$image, 'path' => '/images/water-bodies/nonexistent-photo.jpg']));

        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $response = $this->get(route('map.index'))->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame(2, $xpath->query('//template[starts-with(@id,"profile-")]//p[@data-image-placeholder]')->length);
        foreach (['Hulugan Falls', 'Bukal Falls', 'Bunga Twin Falls'] as $name) {
            $lake = WaterBody::where('name', $name)->sole();
            $this->assertStringContainsString('object-contain', $xpath->evaluate('string(//template[@id="profile-'.$lake->id.'"]//img/@class)'));
        }
    }

    public function test_sidebar_keeps_unlocated_profiles_selectable_without_publishing_geometry(): void
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
        foreach (['all' => '28', 'lake' => '10', 'river' => '5', 'waterfall' => '13'] as $category => $count) {
            $this->assertSame($count, trim($xpath->evaluate('string(//button[@data-category="'.$category.'"]//span[@data-category-count])')));
        }
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $this->assertSame(0, $xpath->query('//li[@data-water-row="'.$lake->id.'"][@hidden]')->length);
        $this->assertNull(collect($response->viewData('lakes'))->firstWhere('id', $lake->id)['geography']);
    }

    public function test_profile_sections_keep_classification_fish_reports_and_scientific_evidence_distinct(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $response = $this->get(route('map.index'))->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame(4, $xpath->query('//div[@id="profile-tabs"]/button[@role="tab"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-profile-section="pollution"]')->length);
        foreach ($response->viewData('lakes') as $record) {
            $root = '//template[@id="profile-'.$record['id'].'"]';
            foreach (['overview', 'classification', 'fish', 'research'] as $section) {
                $panels = $xpath->query($root.'//section[@data-profile-section="'.$section.'"]');
                $this->assertSame(1, $panels->length);
                $this->assertFalse($panels->item(0)->hasAttribute('hidden'));
            }
            $this->assertSame(1, $xpath->query($root.'//a[contains(@class,"profile-report-link")]')->length);
        }
        $this->assertSame('Profile', trim($xpath->evaluate('string(//button[@data-profile-tab="overview"])')));
        $this->assertSame('Water Type', trim($xpath->evaluate('string(//button[@data-profile-tab="classification"])')));
        $response->assertSee('Historical aquaculture')->assertSee('Plapla')->assertSee('Name source')
            ->assertSee('General geographic assessment')->assertSee('OpenStreetMap contributors')->assertSee('Image attribution');
        $this->assertSame(26, $xpath->query('//template[starts-with(@id,"profile-")]//img[@data-water-image]')->length);
        $this->assertSame(2, $xpath->query('//template[starts-with(@id,"profile-")]//p[@data-image-placeholder]')->length);
    }

    #[TestWith(['Pagsanjan Falls', ['Cavinti Falls', 'Magdapio Falls']])]
    #[TestWith(['Taytay Falls', ['Majayjay Falls']])]
    public function test_waterfall_primary_names_remain_distinct_from_research_aliases(string $name, array $aliases): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $before = WaterBody::orderBy('id')->get()->toArray();
        $lake = WaterBody::where('name', $name)->sole();

        $response = $this->get(route('map.index'))->assertOk();

        $record = collect($response->viewData('lakes'))->sole('id', $lake->id);
        $this->assertSame($name, $record['name']);
        $this->assertSame($aliases, $record['aliases']);
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $root = '//template[@id="profile-'.$lake->id.'"]';
        $this->assertSame($name, trim($xpath->evaluate('string('.$root.'//h2)')));
        $researchNames = $xpath->evaluate('string('.$root.'//section[@data-profile-section="research"]//*[@data-alternative-names])');
        foreach ($aliases as $alias) {
            $this->assertStringContainsString($alias, $researchNames);
            $this->assertStringNotContainsString($alias, $xpath->evaluate('string('.$root.'//section[@data-profile-section="overview"])'));
            $this->assertDatabaseMissing('water_bodies', ['name' => $alias]);
        }

        $form = $this->get(route('reports.create'))->assertOk();
        libxml_use_internal_errors(true);
        $document->loadHTML($form->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame($name, trim($xpath->evaluate('string(//select[@id="water_body_id"]//option[@value="'.$lake->id.'"])')));
        $this->assertSame($before, WaterBody::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('water_bodies', 28);
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
        $this->assertDatabaseMissing('water_bodies', ['name' => 'Hidden Valley Springs']);
        $this->assertDatabaseCount('fish_species', 1);
        $this->assertDatabaseCount('water_body_fish', 3);
        $this->assertDatabaseCount('pollution_reports', 0);
    }
}
