<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\User;
use App\Models\WaterBody;
use App\Support\ExplorerProfile;
use App\Support\WaterBodyMetrics;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class RiverExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_import_preserves_existing_ids_metadata_reports_and_fish_and_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $river = WaterBody::where('name', 'Balanac River')->sole();
        PollutionReport::forceCreate(['water_body_id' => $river->id, 'status' => 'pending', 'pollution_type' => 'other', 'description' => 'Private original report']);
        $before = WaterBody::orderBy('id')->get()->toArray();
        $reports = PollutionReport::all()->toArray();

        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);

        $this->assertDatabaseCount('water_bodies', 54);
        $this->assertSame(29, WaterBody::where('category', 'river')->count());
        $this->assertSame($before, WaterBody::whereIn('id', array_column($before, 'id'))->orderBy('id')->get()->toArray());
        $this->assertSame($reports, PollutionReport::all()->toArray());
        $this->assertDatabaseCount('water_body_fish', 3);
        $this->assertSame(0, WaterBody::whereNotIn('id', array_column($before, 'id'))->withCount('fishSpecies')->get()->sum('fish_species_count'));
        $imported = WaterBody::orderBy('id')->get()->toArray();
        $this->travel(1)->hours();
        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);
        $this->assertSame($imported, WaterBody::orderBy('id')->get()->toArray());
    }

    public function test_alias_collision_rolls_back_the_entire_additive_import(): void
    {
        WaterBody::forceCreate(['name' => 'Existing river', 'municipality' => 'Fixture', 'water_type' => 'Not Assessed', 'category' => 'river', 'aliases' => ['Binan River']]);
        $before = WaterBody::all()->toArray();
        $entries = json_decode(file_get_contents(database_path('data/river-expansion.json')), true);
        try {
            (new ExplorerSeeder)->import($entries);
            $this->fail('An ambiguous alias must abort the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Ambiguous explorer identity', $exception->getMessage());
        }
        $this->assertSame($before, WaterBody::all()->toArray());
    }

    public function test_import_refuses_an_unapproved_database_before_connecting(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unapproved_target']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires aquamap_laguna');
        (new ExplorerSeeder)->import(json_decode(file_get_contents(database_path('data/river-expansion.json')), true));
    }

    public function test_all_provincial_profiles_are_selectable_and_unmapped_rivers_have_no_fake_point(): void
    {
        $this->seed(ExplorerSeeder::class);
        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);

        $response = $this->get(route('map.index'))->assertOk()->assertSee('River course not yet mapped.')
            ->assertSee('Length not yet verified.')->assertSee('Mapped segment length:')->assertSee('Clear selection');
        $records = collect($response->viewData('lakes'))->keyBy('name');
        $this->assertCount(52, $records);
        $this->assertCount(29, $records->where('category', 'river'));
        $this->assertCount(17, $records->where('category', 'river')->filter(fn (array $record): bool => $record['geography'] !== null));
        $this->assertNull($records['San Isidro River']['geography']);
        $this->assertNull($records['San Isidro River']['latitude']);
        $this->assertNull($records['San Isidro River']['longitude']);
        $this->assertSame('LineString', $records['San Pedro River']['geography']['geometry']['type']);
        $this->assertSame('LineString', $records['Tunasan River']['geography']['geometry']['type']);
        $this->get(route('reports.create'))->assertOk()->assertSee('San Isidro River')->assertSee('San Pedro River')->assertSee('Matikiw River');
    }

    public function test_source_lengths_remain_separate_from_partial_geometry_and_ambiguous_lengths_stay_unknown(): void
    {
        $this->seed(ExplorerSeeder::class);
        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);

        $response = $this->get(route('map.index'))->assertOk();
        $profiles = $response->viewData('profiles');
        $river = WaterBody::where('name', 'Balanac River')->sole();
        $this->assertSame(10.7, $profiles[$river->id]['reported_length']['value']);
        $this->assertNotSame(10.7, $profiles[$river->id]['mapped_length_km']);
        $this->assertStringContainsString('Table 33', $profiles[$river->id]['reported_length']['reference']);
        $this->assertNull($profiles[WaterBody::where('name', 'Bumbungan River')->sole()->id]['reported_length']);
        $this->assertNull($profiles[WaterBody::where('name', 'San Pedro River')->sole()->id]['reported_length']);
        $this->assertCount(26, array_filter($profiles, fn (array $profile): bool => $profile['reported_length'] !== null));
        $this->assertSame(30.5, $profiles[WaterBody::where('name', 'Yambo Lake')->sole()->id]['surface_area']['value']);
        $this->assertNull($profiles[WaterBody::where('name', 'Yambo Lake')->sole()->id]['mapped_length_km']);
    }

    public function test_great_circle_length_measures_each_part_without_bridging_gaps(): void
    {
        $geography = ['status' => 'source_verified', 'crs' => 'EPSG:4326', 'checked_on' => '2026-10-21', 'source_url' => 'https://example.test/river',
            'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[0, 0], [1, 0]], [[100, 0], [101, 0]]]]];
        $this->assertSame(222.39, WaterBodyMetrics::mappedLength($geography));
        $geography['geometry'] = ['type' => 'LineString', 'coordinates' => [[0, 60], [1, 60]]];
        $this->assertSame(55.597, WaterBodyMetrics::mappedLength($geography));
        $geography['status'] = 'unverified';
        $this->assertNull(WaterBodyMetrics::mappedLength($geography));
    }

    #[TestWith([['type' => 'LineString', 'coordinates' => [[121, 14], [121, 14]]]])]
    #[TestWith([['type' => 'LineString', 'coordinates' => [[14, 121], [14, 122]]]])]
    #[TestWith([['type' => 'MultiLineString', 'coordinates' => [[[121, 14]]]]])]
    #[TestWith([['type' => 'LineString', 'coordinates' => [['121', 14], [122, 14]]]])]
    public function test_invalid_river_lines_are_never_published_or_measured(array $geometry): void
    {
        $geography = ['status' => 'source_verified', 'crs' => 'EPSG:4326', 'checked_on' => '2026-10-21', 'source_url' => 'https://example.test/river', 'geometry' => $geometry];
        $this->assertNull(ExplorerProfile::geography($geography, 'river'));
        $this->assertNull(WaterBodyMetrics::mappedLength($geography));
    }

    public function test_a_watershed_polygon_cannot_be_displayed_as_a_river_course(): void
    {
        $this->seed(ExplorerSeeder::class);
        $river = WaterBody::where('name', 'Santa Cruz River')->sole();
        $river->forceFill(['geography' => WaterBody::where('name', 'Sampaloc Lake')->sole()->geography])->save();

        $response = $this->get(route('map.index'))->assertOk();
        $this->assertNull(collect($response->viewData('lakes'))->firstWhere('id', $river->id)['geography']);
        $this->assertNull($response->viewData('profiles')[$river->id]['mapped_length_km']);
    }

    public function test_unverified_wrong_identity_and_unsafe_metric_sources_are_not_accepted(): void
    {
        $entry = ['municipality' => 'Bay', 'status' => 'source_verified', 'value' => 5.8, 'source_url' => 'https://example.test/source', 'reference' => 'Table 1', 'note' => 'Approximate'];
        $this->assertNull(WaterBodyMetrics::reported($entry, 'Calamba City'));
        $entry['status'] = 'unverified';
        $this->assertNull(WaterBodyMetrics::reported($entry, 'Bay'));
        $entry['status'] = 'source_verified';
        $entry['source_url'] = 'javascript:alert(1)';
        $this->assertNull(WaterBodyMetrics::reported($entry, 'Bay'));
    }

    #[TestWith(['San Isidro River'])]
    #[TestWith(['Calo River'])]
    public function test_new_river_reports_stay_pending_private_and_readable_by_administrators(string $name): void
    {
        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);
        $water = WaterBody::where('name', $name)->sole();
        $form = $this->get(route('reports.create'))->assertOk();
        $this->travel(3)->seconds();

        $this->post(route('reports.store'), ['water_body_category' => 'river', 'water_body_id' => $water->id,
            'pollution_type' => 'other', 'description' => 'Private isolated river concern', 'status' => 'verified',
            'report_form_token' => $form->viewData('reportFormToken')])->assertRedirectToRoute('reports.create')->assertSessionHasNoErrors();

        $report = PollutionReport::sole();
        $this->assertSame('pending', $report->status);
        $this->assertSame($water->id, $report->water_body_id);
        $this->assertNull($report->latitude);
        $this->assertNull($report->longitude);
        $this->assertDatabaseCount('report_reviews', 0);
        $this->get(route('map.index'))->assertOk()->assertDontSee('Private isolated river concern');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.reports.index', ['q' => $name]))->assertOk()->assertSee($name);
        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee($name)->assertSee('Private isolated river concern');
    }
}
