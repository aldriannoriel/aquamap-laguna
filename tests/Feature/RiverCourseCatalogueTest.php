<?php

namespace Tests\Feature;

use App\Models\WaterBody;
use App\Support\ExplorerProfile;
use App\Support\RiverCourseCatalogue;
use App\Support\WaterBodyMetrics;
use Database\Seeders\ExplorerSeeder;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class RiverCourseCatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_balanac_highlight_extends_upstream_without_editing_any_existing_record(): void
    {
        $this->seed(ExplorerSeeder::class);
        $river = WaterBody::where('name', 'Balanac River')->sole();
        $before = WaterBody::orderBy('id')->get()->toArray();

        $response = $this->get(route('map.index'))->assertOk()->assertSee('Mapped course length:')
            ->assertSee('Provincial reported figure:')->assertSee('Whole-river length and complete headwater-to-mouth coverage are not independently verified.');
        $mapped = collect($response->viewData('lakes'))->firstWhere('id', $river->id);
        $profile = $response->viewData('profiles')[$river->id];
        $points = array_merge(...$mapped['geography']['geometry']['coordinates']);
        $this->assertLessThan(14.18, min(array_column($points, 1)));
        $this->assertGreaterThan(14.27, max(array_column($points, 1)));
        $this->assertGreaterThan(19, $profile['mapped_length_km']);
        $this->assertCount(20, $profile['course']['source_objects']);
        $this->assertSame(10.7, $profile['reported_length']['value']);
        $this->assertFalse($profile['course']['whole_river_length_verified']);
        $this->assertSame($before, WaterBody::orderBy('id')->get()->toArray());
        $this->assertStringContainsString('Pagsanjan only', $profile['original_geography_note']);
    }

    public function test_all_29_rivers_have_an_explicit_coverage_audit_with_no_invented_missing_courses(): void
    {
        $this->seed(ExplorerSeeder::class);
        $this->artisan('rivers:import', ['--no-interaction' => true])->assertExitCode(0);
        $before = WaterBody::orderBy('id')->get()->toArray();
        $catalogue = RiverCourseCatalogue::load();
        $this->assertCount(29, $catalogue['rivers']);
        $mapped = 0;
        $missing = [];
        foreach (WaterBody::where('category', 'river')->get() as $river) {
            $resolved = RiverCourseCatalogue::resolve($river, $catalogue);
            $this->assertNotNull($resolved['course'], $river->name);
            $this->assertFalse($resolved['course']['whole_river_length_verified']);
            if ($resolved['course']['coverage'] === 'unmapped') {
                $this->assertNull($resolved['geography']);
                $this->assertNull(WaterBodyMetrics::mappedLength($resolved['geography']));
                $missing[] = $river->name;
            } else {
                $this->assertSame(2, $resolved['course']['endpoint_count']);
                $this->assertGreaterThan(0, WaterBodyMetrics::mappedLength($resolved['geography']));
                $this->assertSame('https://opendatacommons.org/licenses/odbl/1-0/', $resolved['geography']['license_url']);
                $mapped++;
            }
        }
        $this->assertSame(17, $mapped);
        $this->assertCount(12, $missing);
        $this->assertContains('Romero River', $missing);
        $this->assertContains('San Isidro River', $missing);
        $this->artisan('rivers:audit-courses', ['--json' => true, '--no-interaction' => true])
            ->expectsOutputToContain('"valid": true')->assertExitCode(0);
        $this->assertSame($before, WaterBody::orderBy('id')->get()->toArray());
    }

    #[TestWith(['wrong_name'])]
    #[TestWith(['road'])]
    #[TestWith(['unsafe_url'])]
    #[TestWith(['future_revision'])]
    #[TestWith(['coordinate_count'])]
    #[TestWith(['disconnected'])]
    #[TestWith(['duplicate_edge'])]
    #[TestWith(['branch'])]
    public function test_unverified_source_objects_never_replace_the_original_verified_reach(string $failure): void
    {
        $this->seed(ExplorerSeeder::class);
        $river = WaterBody::where('name', 'Balanac River')->sole();
        $catalogue = RiverCourseCatalogue::load();
        $entry = &$catalogue['rivers']['Balanac River'];
        if ($failure === 'wrong_name') {
            $entry['source_objects'][0]['name'] = 'Botocan River';
        } elseif ($failure === 'road') {
            $entry['source_objects'][0]['waterway'] = 'highway';
        } elseif ($failure === 'unsafe_url') {
            $entry['source_objects'][0]['url'] = 'javascript:alert(1)';
        } elseif ($failure === 'future_revision') {
            $entry['source_objects'][0]['timestamp'] = '2999-01-01T00:00:00Z';
        } elseif ($failure === 'coordinate_count') {
            array_pop($entry['geography']['geometry']['coordinates'][0]);
        } else {
            $object = $entry['source_objects'][0];
            $line = $entry['geography']['geometry']['coordinates'][0];
            $object['id'] = 999999999;
            $object['url'] = 'https://www.openstreetmap.org/way/999999999';
            if ($failure === 'disconnected') {
                $object['node_ids'] = array_map(fn (int $id): int => $id + 100000000000, $object['node_ids']);
            } elseif ($failure === 'duplicate_edge') {
                $object['node_ids'] = array_reverse($object['node_ids']);
                $line = array_reverse($line);
            } else {
                $object['node_ids'] = [$object['node_ids'][1], 999999999999];
                $line = [$line[1], [$line[1][0] + 0.0001, $line[1][1] + 0.0001]];
            }
            $entry['source_objects'][] = $object;
            $entry['geography']['geometry']['coordinates'][] = $line;
        }

        $resolved = RiverCourseCatalogue::resolve($river, $catalogue);

        $this->assertNull($resolved['course']);
        $this->assertSame(ExplorerProfile::geography($river->geography, 'river'), $resolved['geography']);
    }

    public function test_an_identity_or_baseline_source_conflict_does_not_apply_the_supplement(): void
    {
        $this->seed(ExplorerSeeder::class);
        $river = WaterBody::where('name', 'Balanac River')->sole();
        $catalogue = RiverCourseCatalogue::load();
        $catalogue['rivers']['Balanac River']['municipality'] = 'Unrelated municipality';
        $this->assertNull(RiverCourseCatalogue::resolve($river, $catalogue)['course']);
        $catalogue = RiverCourseCatalogue::load();
        $catalogue['rivers']['Balanac River']['baseline_source_url'] = 'https://example.test/unrelated';
        $this->assertNull(RiverCourseCatalogue::resolve($river, $catalogue)['course']);
    }

    public function test_audit_refuses_an_unapproved_database_before_connecting(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unapproved_target']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Course audit requires aquamap_laguna');
        $this->artisan('rivers:audit-courses', ['--no-interaction' => true])->run();
    }

    public function test_malformed_legacy_aliases_and_reach_notes_do_not_break_the_map(): void
    {
        $this->seed(ExplorerSeeder::class);
        $river = WaterBody::where('name', 'Balanac River')->sole();
        $geography = $river->geography;
        $geography['note'] = ['Malformed historical note'];
        $river->forceFill(['aliases' => 'Malformed historical alias', 'geography' => $geography])->save();

        $response = $this->get(route('map.index'))->assertOk();
        $profile = $response->viewData('profiles')[$river->id];
        $this->assertCount(20, $profile['course']['source_objects']);
        $this->assertNull($profile['original_geography_note']);
    }

    public function test_ellipsoidal_length_handles_meridians_and_the_antimeridian_without_degree_lengths(): void
    {
        $geography = ['status' => 'source_verified', 'crs' => 'EPSG:4326', 'source_url' => 'https://example.test/geometry', 'checked_on' => '2026-10-21',
            'geometry' => ['type' => 'LineString', 'coordinates' => [[0, 0], [0, 1]]]];
        $this->assertSame(110.574, WaterBodyMetrics::mappedLength($geography));
        $geography['geometry']['coordinates'] = [[179.9, 0], [-179.9, 0]];
        $this->assertSame(22.264, WaterBodyMetrics::mappedLength($geography));
        $geography['geometry']['coordinates'] = [[0, 0], [179.99999, 0.00001]];
        $this->assertNull(WaterBodyMetrics::mappedLength($geography));
    }
}
