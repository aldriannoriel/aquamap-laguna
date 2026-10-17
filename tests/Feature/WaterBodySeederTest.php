<?php

namespace Tests\Feature;

use App\Models\WaterBody;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WaterBodySeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_seeds_three_sourced_freshwater_lakes_without_scientific_or_user_records(): void
    {
        $this->seed(WaterBodySeeder::class);

        $this->assertDatabaseCount('water_bodies', 3);
        foreach ([
            ['Sampaloc Lake', '14.0791000', '121.3299000'],
            ['Pandin Lake', '14.1160000', '121.3670000'],
            ['Yambo Lake', '14.1186000', '121.3660000'],
        ] as [$name, $latitude, $longitude]) {
            $lake = WaterBody::where('name', $name)->sole();
            $this->assertSame('San Pablo City', $lake->municipality);
            $this->assertSame('freshwater', $lake->water_type);
            $this->assertSame($latitude, $lake->latitude);
            $this->assertSame($longitude, $lake->longitude);
            $this->assertSame('https://nfrdi.da.gov.ph/tpjf/vol32_2/research_article_ortiz_et_al_32220240111.pdf', $lake->source_url);
        }
        $this->assertStringContainsString('Nagcarlan', WaterBody::where('name', 'Yambo Lake')->sole()->description);
        $this->assertStringContainsString('Rizal', WaterBody::where('name', 'Yambo Lake')->sole()->description);
        $this->assertDatabaseCount('fish_species', 0);
        $this->assertDatabaseCount('water_body_fish', 0);
        $this->assertDatabaseCount('pollution_reports', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reseeding_keeps_ids_and_updates_verified_fields_without_touching_other_lakes(): void
    {
        $this->seed(WaterBodySeeder::class);
        $ids = WaterBody::orderBy('name')->pluck('id', 'name')->all();
        WaterBody::where('name', 'Pandin Lake')->update(['source_url' => null]);
        $unrelated = WaterBody::create([
            'name' => 'Isolated test fixture',
            'municipality' => 'Fixture locality',
            'water_type' => 'fixture',
            'latitude' => '0',
            'longitude' => '0',
        ]);
        $unrelated->refresh();

        $this->seed(WaterBodySeeder::class);

        $this->assertDatabaseCount('water_bodies', 4);
        $this->assertSame($ids, WaterBody::where('municipality', 'San Pablo City')->orderBy('name')->pluck('id', 'name')->all());
        $this->assertNotNull(WaterBody::where('name', 'Pandin Lake')->sole()->source_url);
        $this->assertSame($unrelated->getAttributes(), $unrelated->fresh()->getAttributes());
    }

    public function test_unchanged_reseed_preserves_complete_records_across_time(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 17)->startOfDay());
        $this->seed(DatabaseSeeder::class);
        $original = WaterBody::orderBy('id')->get()->toArray();
        $this->travel(1)->days();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($original, WaterBody::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_refuses_a_different_configured_database_before_connecting(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unapproved_day1_target']);
        DB::purge('mysql');
        $connection = DB::connection('mysql');
        $unopenedPdo = $connection->getRawPdo();

        try {
            (new WaterBodySeeder)->run();
            $this->fail('An unauthorized database must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertSame('WaterBodySeeder requires the aquamap_laguna MySQL database.', $exception->getMessage());
            $this->assertSame($unopenedPdo, $connection->getRawPdo());
            $this->assertDatabaseCount('water_bodies', 0, 'sqlite');
        }
    }

    public function test_refuses_an_unexpected_connected_database_before_writing(): void
    {
        $connection = Mockery::mock(MySqlConnection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('aquamap_laguna');
        $connection->shouldReceive('selectOne')->once()
            ->with('SELECT DATABASE() AS name', [], false)
            ->andReturn((object) ['name' => 'unapproved_day1_target']);
        $connection->shouldNotReceive('transaction');
        $originalResolver = WaterBody::getConnectionResolver();
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->with(null)->andReturn($connection);
        WaterBody::setConnectionResolver($resolver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WaterBodySeeder refused an unexpected connected database.');

        try {
            (new WaterBodySeeder)->run();
        } finally {
            WaterBody::setConnectionResolver($originalResolver);
        }
    }

    public function test_refuses_sqlite_outside_the_testing_environment(): void
    {
        $this->app->instance('env', 'local');

        try {
            (new WaterBodySeeder)->run();
            $this->fail('SQLite must be restricted to isolated tests.');
        } catch (RuntimeException $exception) {
            $this->assertSame('WaterBodySeeder requires the aquamap_laguna MySQL database.', $exception->getMessage());
            $this->assertDatabaseCount('water_bodies', 0);
        }
    }

    public function test_failed_seed_rolls_back_all_lake_inserts(): void
    {
        WaterBody::creating(function (WaterBody $lake): void {
            if ($lake->name === 'Yambo Lake') {
                throw new RuntimeException('Isolated test failure.');
            }
        });

        try {
            (new WaterBodySeeder)->run();
            $this->fail('The simulated insert failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Isolated test failure.', $exception->getMessage());
            $this->assertDatabaseCount('water_bodies', 0);
        }
    }
}
