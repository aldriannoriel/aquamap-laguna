<?php

namespace Tests\Feature;

use App\Models\WaterBody;
use App\Support\ExplorerProfile;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class WaterfallExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_targeted_import_adds_only_the_two_missing_waterfalls_and_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $seeder = new ExplorerSeeder;
        $originalCatalogue = [...json_decode(file_get_contents(database_path('data/explorer.json')), true), ...json_decode(file_get_contents(database_path('data/missing-water-bodies.json')), true)];
        $seeder->import($originalCatalogue);
        $before = WaterBody::orderBy('id')->get()->toArray();

        $this->artisan('waterfalls:import', ['--no-interaction' => true])->assertExitCode(0);

        $this->assertDatabaseCount('water_bodies', 30);
        $this->assertSame($before, WaterBody::whereIn('id', array_column($before, 'id'))->orderBy('id')->get()->toArray());
        $this->assertSame(['Balon-Balon Falls', 'Ambon-Ambon Falls'], WaterBody::whereNotIn('id', array_column($before, 'id'))->orderBy('id')->pluck('name')->all());
        $this->assertDatabaseCount('water_body_fish', 3);
        $this->assertDatabaseCount('pollution_reports', 0);
        $imported = WaterBody::orderBy('id')->get()->toArray();
        $this->travel(1)->hours();
        $this->artisan('waterfalls:import', ['--no-interaction' => true])->assertExitCode(0);
        $this->assertSame($imported, WaterBody::orderBy('id')->get()->toArray());
        $response = $this->get(route('map.index'))->assertOk()->assertSee('approximate photo-location reference')->assertSee('not surveyed positional accuracy');
        $this->assertCount(30, $response->viewData('lakes'));
        $this->assertSame(13, collect($response->viewData('lakes'))->where('category', 'waterfall')->count());
    }

    public function test_an_existing_alias_conflict_aborts_the_entire_targeted_import(): void
    {
        WaterBody::forceCreate(['name' => 'Existing waterfall', 'municipality' => 'Fixture', 'water_type' => 'Not assessed', 'latitude' => 14, 'longitude' => 121, 'category' => 'waterfall', 'aliases' => ['Ambon Ambon Falls']]);
        $before = WaterBody::all()->toArray();
        $data = json_decode(file_get_contents(database_path('data/waterfall-expansion.json')), true);
        try {
            (new ExplorerSeeder)->import($data);
            $this->fail('Alias conflicts must require review.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Ambiguous explorer identity', $exception->getMessage());
        }
        $this->assertSame($before, WaterBody::all()->toArray());
    }

    public function test_document_photo_replacements_have_permission_provenance_and_preserve_original_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        $before = WaterBody::orderBy('id')->get()->toArray();
        $response = $this->get(route('map.index'))->assertOk();
        $photos = json_decode(file_get_contents(database_path('data/waterfall-photos.json')), true);
        $this->assertCount(9, $photos);
        foreach ($photos as $name => $photo) {
            $lake = WaterBody::where('name', $name)->sole();
            $image = $response->viewData('profiles')[$lake->id]['image'];
            $this->assertSame($photo['image']['path'], $image['path']);
            $this->assertSame($photo['image']['author'], $image['author']);
            $this->assertSame($photo['image']['source_url'], $image['source_url']);
            $this->assertSame(url('/images/water-bodies/document-photo-permission.html'), $image['license_url']);
            $this->assertStringContainsString('permission confirmed by the project owner', $image['license']);
            $this->assertStringContainsString('2026-10-20', $photo['permission_basis']);
            $this->assertSame($photo['optimized_sha256'], hash_file('sha256', public_path($image['path'])));
            $dimensions = getimagesize(public_path($image['path']));
            $this->assertLessThanOrEqual(960, max($dimensions[0], $dimensions[1]));
            $this->assertLessThan(150000, filesize(public_path($image['path'])));
        }
        $this->assertSame($before, WaterBody::orderBy('id')->get()->toArray());
        foreach (['Hulugan Falls', 'Buruwisan Falls', 'Taytay Falls', 'Pagsanjan Falls'] as $name) {
            $lake = WaterBody::where('name', $name)->sole();
            $this->assertStringNotContainsString('/document-', $response->viewData('profiles')[$lake->id]['image']['path']);
        }
    }

    public function test_targeted_import_refuses_an_unapproved_database_before_connecting(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unapproved_target']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires aquamap_laguna');
        (new ExplorerSeeder)->import(json_decode(file_get_contents(database_path('data/waterfall-expansion.json')), true));
    }

    public function test_source_check_dates_follow_laguna_calendar_days_and_still_reject_future_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 23:30:00', 'UTC'));
        $geography = json_decode(file_get_contents(database_path('data/waterfall-expansion.json')), true)[0]['geography'];
        $this->assertNotNull(ExplorerProfile::geography($geography));
        $geography['checked_on'] = '2026-10-21';
        $this->assertNull(ExplorerProfile::geography($geography));
    }
}
