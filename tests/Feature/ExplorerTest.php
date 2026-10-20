<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\MySqlConnection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class ExplorerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_import_is_repeatable_and_preserves_original_attributes_ids_and_fish_links(): void
    {
        $this->seed(DatabaseSeeder::class);
        $original = WaterBody::orderBy('id')->get()->map(fn (WaterBody $lake): array => $lake->only(['id', 'name', 'municipality', 'latitude', 'longitude', 'description', 'source_url']) + ['created_at' => $lake->created_at->toISOString()])->all();
        $this->seed(ExplorerSeeder::class);
        $this->assertDatabaseCount('water_bodies', 30);
        $this->assertDatabaseCount('water_body_fish', 3);
        $this->assertSame($original, WaterBody::orderBy('id')->limit(3)->get()->map(fn (WaterBody $lake): array => $lake->only(['id', 'name', 'municipality', 'latitude', 'longitude', 'description', 'source_url']) + ['created_at' => $lake->created_at->toISOString()])->all());
        $before = WaterBody::orderBy('id')->get()->toArray();
        $this->travel(1)->hours();
        $this->seed(ExplorerSeeder::class);
        $this->assertSame($before, WaterBody::orderBy('id')->get()->toArray());
        $this->get(route('map.index'))->assertOk()->assertSee('Historical aquaculture')->assertSee('July-September 2017');
    }

    public function test_catalog_exposes_verified_geometry_categories_and_explicit_limitations(): void
    {
        $this->seed(WaterBodySeeder::class);
        $this->seed(ExplorerSeeder::class);
        $response = $this->get(route('map.index'))->assertOk()->assertSee('All Water Bodies')->assertDontSee('Hot Springs / Natural Springs')
            ->assertSee('Al Fresco Springs Resort')->assertSee('Hidden Valley Springs')->assertSee('Water type not yet verified')
            ->assertSee('Publication date not provided')->assertSee('not a survey');
        $records = collect($response->viewData('lakes'))->keyBy('name');
        $this->assertSame(['lake' => 10, 'river' => 5, 'spring' => 2, 'waterfall' => 13], collect($response->viewData('lakes'))->countBy('category')->sortKeys()->all());
        $this->assertSame('Polygon', $records['Sampaloc Lake']['geography']['geometry']['type']);
        $this->assertSame('MultiLineString', $records['Bumbungan River']['geography']['geometry']['type']);
        $this->assertSame([121.4999861, 14.2625628], $records['Pagsanjan Falls']['geography']['geometry']['coordinates']);
        $this->assertSame(['Cavinti Falls', 'Magdapio Falls'], $records['Pagsanjan Falls']['aliases']);
        foreach ($records as $record) {
            $this->assertSame('EPSG:4326', $record['geography']['crs']);
            $this->assertSame('source_verified', $record['geography']['status']);
            $this->assertSame(['id', 'name', 'municipality', 'water_type', 'latitude', 'longitude', 'description', 'source_url', 'category', 'aliases', 'site_type', 'spring_type', 'official_url', 'geography'], array_keys($record));
        }
    }

    #[TestWith(['status', 'unverified'])]
    #[TestWith(['crs', 'EPSG:3857'])]
    #[TestWith(['source_url', 'javascript:alert(1)'])]
    #[TestWith(['checked_on', null])]
    #[TestWith(['checked_on', '2026-02-30'])]
    #[TestWith(['checked_on', '2999-01-01'])]
    #[TestWith(['geometry', ['type' => 'Point', 'coordinates' => [181, 14]]])]
    #[TestWith(['geometry', ['type' => 'Point', 'coordinates' => ['121.3', 14]]])]
    #[TestWith(['geometry', ['type' => 'Polygon', 'coordinates' => [[[121, 14], [122, 14], [122, 15], [121, 15]]]]])]
    #[TestWith(['geometry', ['type' => 'MultiLineString', 'coordinates' => []]])]
    #[TestWith(['geometry', ['type' => 'FeatureCollection', 'features' => []]])]
    public function test_unverified_or_malformed_geography_never_becomes_a_public_map_feature(string $field, mixed $value): void
    {
        $this->seed(WaterBodySeeder::class);
        $this->seed(ExplorerSeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $geography = $lake->geography;
        $geography[$field] = $value;
        $lake->forceFill(['geography' => $geography])->save();
        $response = $this->get(route('map.index'))->assertOk()->assertSee('Location awaiting verification');
        $record = collect($response->viewData('lakes'))->firstWhere('id', $lake->id);
        $this->assertNull($record['geography']);
        $this->assertSame($lake->latitude, $record['latitude']);
    }

    public function test_no_report_counts_or_contents_are_serialized_publicly(): void
    {
        $this->seed(WaterBodySeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        foreach (['verified', 'verified', 'pending', 'rejected', 'resolved'] as $state) {
            PollutionReport::forceCreate(['water_body_id' => $lake->id, 'status' => $state, 'pollution_type' => 'other',
                'description' => 'Private '.$state.' observation fixture', 'reporter_name' => 'Private Fixture', 'reporter_email' => 'private@example.test', 'photo_path' => 'private-photo.jpg']);
        }
        $response = $this->get(route('map.index'))->assertOk()->assertDontSee('private@example.test')->assertDontSee('Private Fixture')->assertDontSee('private-photo.jpg');
        foreach (['verified', 'pending', 'rejected', 'resolved'] as $state) {
            $response->assertDontSee('Private '.$state.' observation fixture');
        }
        $record = collect($response->viewData('lakes'))->firstWhere('id', $lake->id);
        $this->assertArrayNotHasKey('verified_report_count', $record);
        $response->assertDontSee('currently verified community reports.')->assertDontSee('data-report-category', false);
    }

    public function test_report_categories_and_legacy_types_remain_private_for_every_water_body(): void
    {
        $this->seed(WaterBodySeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $otherLake = WaterBody::where('name', 'Pandin Lake')->sole();
        foreach (['plastic_waste', 'plastic_waste', 'floating_garbage', 'illegal_dumping', 'solid_waste', '<script>privateLegacyType</script>'] as $type) {
            PollutionReport::forceCreate(['water_body_id' => $lake->id, 'status' => 'verified', 'pollution_type' => $type,
                'description' => 'Private environmental fixture', 'latitude' => '14.1234567', 'longitude' => '121.7654321']);
        }
        foreach (['pending', 'resolved', 'rejected', 'legacy_state'] as $status) {
            PollutionReport::forceCreate(['water_body_id' => $lake->id, 'status' => $status, 'pollution_type' => 'oil_chemical', 'description' => 'Unpublished fixture']);
        }
        PollutionReport::forceCreate(['water_body_id' => $otherLake->id, 'status' => 'verified', 'pollution_type' => 'fish_death', 'description' => 'Another location fixture']);
        $before = PollutionReport::orderBy('id')->get()->toArray();

        $response = $this->get(route('map.index'))->assertOk()
            ->assertDontSee('Unresolved verified observations:')->assertDontSee('Other legacy category')
            ->assertDontSee('privateLegacyType')->assertDontSee('Private environmental fixture')
            ->assertDontSee('Unpublished fixture')->assertDontSee('Another location fixture')
            ->assertDontSee('14.1234567')->assertDontSee('121.7654321');

        $this->assertArrayNotHasKey('report_categories', $response->viewData('profiles')[$lake->id]);
        $this->assertArrayNotHasKey('verified_report_count', collect($response->viewData('lakes'))->firstWhere('id', $lake->id));
        $this->assertArrayNotHasKey('report_categories', $response->viewData('profiles')[$otherLake->id]);
        $this->assertSame($before, PollutionReport::orderBy('id')->get()->toArray());
    }

    public function test_reference_fields_are_allowlisted_and_escaped_and_unsafe_urls_are_dropped(): void
    {
        $this->seed(WaterBodySeeder::class);
        $lake = WaterBody::first();
        $unsafe = '<img src=x onerror=alert(1)>';
        $lake->forceFill(['research_references' => [
            ['title' => $unsafe, 'publisher' => $unsafe, 'published_on' => $unsafe, 'url' => 'https://example.test/study', 'private_note' => 'Never public'],
            ['title' => 'Unsafe source', 'publisher' => 'Fixture', 'url' => 'javascript:alert(1)'],
            ['title' => 'Credential source', 'publisher' => 'Fixture', 'url' => 'https://user:password@example.test/'],
            ['title' => ['bad shape'], 'publisher' => 'Fixture', 'url' => 'https://example.test/'],
        ], 'official_url' => 'data:text/html,unsafe'])->save();
        $response = $this->get(route('map.index'))->assertOk()->assertSee($unsafe)->assertDontSee($unsafe, false)->assertDontSee('Never public')->assertDontSee('Unsafe source')->assertDontSee('user:password');
        $this->assertCount(1, $response->viewData('profiles')[$lake->id]['references']);
        $this->assertNull(collect($response->viewData('lakes'))->firstWhere('id', $lake->id)['official_url']);
    }

    public function test_only_local_attributed_photos_render_and_unlicensed_or_private_paths_get_placeholders(): void
    {
        $this->seed(WaterBodySeeder::class);
        $lake = WaterBody::first();
        $path = '/images/water-bodies/explorer-fixture-'.bin2hex(random_bytes(6)).'.png';
        $handle = fopen(public_path($path), 'x');
        fwrite($handle, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6Z3UAAAAASUVORK5CYII='));
        fclose($handle);
        try {
            $image = ['path' => $path, 'alt' => 'Synthetic licensed fixture', 'author' => 'Fixture author', 'license' => 'Fixture permission', 'source_url' => 'https://example.test/photo', 'license_url' => 'https://example.test/license'];
            $lake->forceFill(['image' => $image])->save();
            $this->get(route('map.index'))->assertOk()->assertSee('src="'.$path.'"', false)->assertSee('Fixture author')->assertSee('Fixture permission');
            foreach ([array_diff_key($image, ['license' => true]), array_replace($image, ['path' => '/storage/private/report.jpg']), array_replace($image, ['path' => 'https://example.test/photo.jpg'])] as $invalid) {
                $lake->forceFill(['image' => $invalid])->save();
                $this->get(route('map.index'))->assertOk()->assertDontSee('Synthetic licensed fixture')->assertSee('No authorized photograph available.');
            }
        } finally {
            unlink(public_path($path));
        }
    }

    public function test_import_aborts_on_ambiguous_aliases_without_partial_updates(): void
    {
        $this->seed(WaterBodySeeder::class);
        foreach (['Bumbungan River', 'Pagsanjan River'] as $name) {
            WaterBody::forceCreate(['name' => $name, 'municipality' => 'Pagsanjan', 'water_type' => 'fixture', 'latitude' => 14, 'longitude' => 121]);
        }
        try {
            $this->seed(ExplorerSeeder::class);
            $this->fail('Ambiguous identities must not be merged silently.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Ambiguous explorer identity', $exception->getMessage());
        }
        $this->assertDatabaseCount('water_bodies', 5);
        $this->assertNull(WaterBody::where('name', 'Sampaloc Lake')->value('geography'));
    }

    public function test_existing_geographic_metadata_is_not_overwritten_by_an_import(): void
    {
        $this->seed(WaterBodySeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $lake->forceFill(['geography' => ['status' => 'owner_review_required']])->save();
        try {
            $this->seed(ExplorerSeeder::class);
            $this->fail('Existing geography requires manual review.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('manual review', $exception->getMessage());
        }
        $this->assertSame(['status' => 'owner_review_required'], $lake->fresh()->geography);
        $this->assertDatabaseCount('water_bodies', 3);
    }

    public function test_an_existing_case_variant_alias_requires_review_instead_of_creating_a_duplicate(): void
    {
        $this->seed(WaterBodySeeder::class);
        WaterBody::forceCreate(['name' => 'pagsanjan river', 'municipality' => 'Pagsanjan', 'water_type' => 'fixture', 'latitude' => 14, 'longitude' => 121, 'category' => 'river']);
        try {
            $this->seed(ExplorerSeeder::class);
            $this->fail('An existing alias requires manual identity review.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Ambiguous explorer identity', $exception->getMessage());
        }
        $this->assertDatabaseCount('water_bodies', 4);
        $this->assertDatabaseMissing('water_bodies', ['name' => 'Bumbungan River']);
    }

    public function test_reporting_uses_only_verified_reference_points_and_accepts_new_water_body_categories(): void
    {
        $this->seed(WaterBodySeeder::class);
        $initial = $this->get(route('reports.create'))->assertOk();
        $this->assertNull($initial->viewData('lakes')->first()->latitude);
        $this->seed(ExplorerSeeder::class);
        $response = $this->get(route('reports.create'))->assertOk()->assertSee('Water body *')->assertSee('Pagsanjan Falls')
            ->assertSee('Al Fresco Springs Resort')->assertSee('Hidden Valley Springs');
        $waterfall = WaterBody::where('name', 'Pagsanjan Falls')->sole();
        $reference = $response->viewData('lakes')->firstWhere('id', $waterfall->id);
        $this->assertCount(30, $response->viewData('lakes'));
        $this->assertSame(14.2625628, $reference->latitude);
        $this->assertSame(121.4999861, $reference->longitude);
        $this->travel(3)->seconds();
        Storage::fake('pollution-reports');
        $this->post(route('reports.store'), ['photo' => UploadedFile::fake()->image('proof.png'), 'water_body_id' => $waterfall->id, 'pollution_type' => 'other', 'description' => 'Isolated fixture only', 'report_form_token' => $response->viewData('reportFormToken'), 'status' => 'verified'])->assertRedirectToRoute('reports.create')->assertSessionHasNoErrors();
        $report = PollutionReport::sole();
        $this->assertSame('pending', $report->status);
        $this->assertNull($report->latitude);
    }

    #[TestWith(['Al Fresco Springs Resort'])]
    #[TestWith(['Hidden Valley Springs'])]
    public function test_spring_property_profiles_accept_private_reports_without_changing_existing_reports(string $name): void
    {
        $this->seed(WaterBodySeeder::class);
        $this->seed(ExplorerSeeder::class);
        $spring = WaterBody::where('name', $name)->sole();
        $existing = PollutionReport::forceCreate(['water_body_id' => $spring->id, 'pollution_type' => 'other', 'status' => 'verified', 'description' => 'Private existing spring observation']);
        $before = $spring->toArray();
        $form = $this->get(route('reports.create'))->assertOk()->assertSee($name);
        $this->travel(3)->seconds();

        Storage::fake('pollution-reports');
        $this->post(route('reports.store'), ['photo' => UploadedFile::fake()->image('proof.png'), 'water_body_id' => $spring->id, 'pollution_type' => 'other', 'description' => 'Rejected isolated fixture', 'report_form_token' => $form->viewData('reportFormToken')])
            ->assertRedirectToRoute('reports.create')->assertSessionHasNoErrors();

        $this->get(route('map.index'))->assertOk()->assertSee($name)->assertDontSee($existing->description);
        $this->assertSame($before, $spring->fresh()->toArray());
        $this->assertSame('verified', $existing->fresh()->status);
        $this->assertDatabaseCount('water_bodies', 30);
        $this->assertDatabaseCount('pollution_reports', 2);
    }

    public function test_import_refuses_an_unapproved_configured_database_without_opening_it(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unapproved_explorer_target']);
        DB::purge('mysql');
        $connection = DB::connection('mysql');
        $unopened = $connection->getRawPdo();
        try {
            (new ExplorerSeeder)->run();
            $this->fail('An unapproved target must not be connected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ExplorerSeeder requires aquamap_laguna or isolated in-memory tests.', $exception->getMessage());
            $this->assertSame($unopened, $connection->getRawPdo());
            $this->assertDatabaseCount('water_bodies', 0, 'sqlite');
        }
    }

    public function test_import_refuses_a_mismatched_connected_database_before_writing(): void
    {
        $connection = Mockery::mock(MySqlConnection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('aquamap_laguna');
        $connection->shouldReceive('selectOne')->once()->with('SELECT DATABASE() AS name', [], false)
            ->andReturn((object) ['name' => 'unapproved_explorer_target']);
        $connection->shouldNotReceive('transaction');
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->with(null)->andReturn($connection);
        $original = WaterBody::getConnectionResolver();
        WaterBody::setConnectionResolver($resolver);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ExplorerSeeder requires aquamap_laguna or isolated in-memory tests.');
        try {
            (new ExplorerSeeder)->run();
        } finally {
            WaterBody::setConnectionResolver($original);
        }
    }
}
