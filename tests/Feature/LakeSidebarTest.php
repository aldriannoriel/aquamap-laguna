<?php

namespace Tests\Feature;

use App\Console\Commands\ImportLakes;
use App\Models\PollutionReport;
use App\Models\WaterBody;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class LakeSidebarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_targeted_lake_import_is_additive_repeatable_and_keeps_profiles_inert_until_selection(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);
        $before = WaterBody::orderBy('id')->get()->toArray();
        $report = PollutionReport::forceCreate(['water_body_id' => $before[0]['id'], 'pollution_type' => 'other', 'description' => 'Existing isolated historical report']);
        $beforeReport = $report->fresh()->toArray();

        $this->artisan('lakes:import', ['--no-interaction' => true])->assertExitCode(0);

        $this->assertDatabaseCount('water_bodies', 53);
        $this->assertSame($before, WaterBody::whereIn('id', array_column($before, 'id'))->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('water_body_fish', 3);
        $this->assertDatabaseCount('pollution_reports', 1);
        $this->assertSame($beforeReport, $report->fresh()->toArray());
        $this->assertDatabaseMissing('water_bodies', ['name' => 'Patola Lake']);
        $lake = WaterBody::where('name', 'Tadlac Lake')->sole();
        $this->assertSame('Los Baños', $lake->municipality);
        $this->assertSame(['Alligator Lake', 'Tadlak Lake', 'Enchanted Lake'], $lake->aliases);
        $this->assertSame(26513229, $lake->geography['osm_way_id']);
        $this->assertSame(17, $lake->geography['osm_version']);
        $ring = $lake->geography['geometry']['coordinates'][0];
        $this->assertCount(57, $ring);
        $this->assertCount(57, $lake->geography['osm_node_ids']);
        $this->assertSame($ring[0], $ring[array_key_last($ring)]);
        $imported = WaterBody::orderBy('id')->get()->toArray();
        $this->travel(1)->hours();
        $this->artisan('lakes:import', ['--no-interaction' => true])->assertExitCode(0);
        $this->assertSame($imported, WaterBody::orderBy('id')->get()->toArray());

        $response = $this->get(route('map.index'))->assertOk()
            ->assertDontSee('All water body profiles &amp; research references', false)
            ->assertDontSee('reference-library')->assertDontSee('reference-grid')
            ->assertSee('Map context &amp; data limits', false)->assertSee('class="report-fab"', false)
            ->assertDontSee('Existing isolated historical report');
        $record = collect($response->viewData('lakes'))->sole('id', $lake->id);
        $this->assertNull($record['water_type']);
        $this->assertSame('Polygon', $record['geography']['geometry']['type']);
        $this->assertSame([], $response->viewData('fishByLake')[$lake->id]);
        $this->assertNull($response->viewData('profiles')[$lake->id]['image']);
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//main//article[starts-with(@id,"profile-")]')->length);
        $this->assertSame(53, $xpath->query('//template[starts-with(@id,"profile-")]')->length);
        foreach ($imported as $water) {
            $root = '//template[@id="profile-'.$water['id'].'"]';
            $this->assertSame(4, $xpath->query($root.'//section[@data-profile-section]')->length);
            $this->assertSame(6, $xpath->query($root.'//*[@data-research-group]')->length);
        }
        $this->assertStringContainsString('llda.gov.ph', $xpath->evaluate('string(//template[@id="profile-'.$lake->id.'"]//section[@data-profile-section="research"])'));
    }

    #[TestWith(['Alligator Lake', []])]
    #[TestWith(['alligator lake', []])]
    #[TestWith(['Tadlak Lake', []])]
    #[TestWith(['Enchanted Lake', []])]
    #[TestWith(['Existing local name', ['Tadlac Lake']])]
    #[TestWith(['Existing local name', ['Alligator Lake']])]
    public function test_existing_alias_cannot_create_a_duplicate_or_overwrite_a_record(string $name, array $aliases): void
    {
        WaterBody::forceCreate(['name' => $name, 'aliases' => $aliases, 'municipality' => 'Los Baños', 'water_type' => 'Not assessed', 'category' => 'lake']);
        $before = WaterBody::all()->toArray();
        try {
            (new ImportLakes)->handle(new ExplorerSeeder);
            $this->fail('Existing alternate identities require review.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Ambiguous explorer identity', $exception->getMessage());
        }
        $this->assertSame($before, WaterBody::all()->toArray());
    }

    public function test_conflicting_existing_metadata_cannot_be_overwritten(): void
    {
        $this->artisan('lakes:import', ['--no-interaction' => true])->assertExitCode(0);
        WaterBody::sole()->forceFill(['research_references' => [['title' => 'Preserved existing research']]])->save();
        $before = WaterBody::all()->toArray();

        try {
            (new ImportLakes)->handle(new ExplorerSeeder);
            $this->fail('Conflicting metadata requires review.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Existing explorer metadata requires manual review', $exception->getMessage());
        }

        $this->assertSame($before, WaterBody::all()->toArray());
    }

    public function test_lake_import_refuses_an_unapproved_database_before_connecting(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unapproved_target']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires aquamap_laguna');
        (new ImportLakes)->handle(new ExplorerSeeder);
    }
}
