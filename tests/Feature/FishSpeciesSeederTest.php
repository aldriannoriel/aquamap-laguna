<?php

namespace Tests\Feature;

use App\Models\FishSpecies;
use App\Models\WaterBody;
use App\Models\WaterBodyFish;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FishSpeciesSeeder;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FishSpeciesSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_seeds_one_sourced_species_and_three_historical_aquaculture_links_without_changing_lakes(): void
    {
        $this->seed(WaterBodySeeder::class);
        $originalLakes = WaterBody::orderBy('id')->get()->toArray();

        $this->seed(FishSpeciesSeeder::class);

        $this->assertDatabaseCount('fish_species', 1);
        $this->assertDatabaseCount('water_body_fish', 3);
        $fish = FishSpecies::sole();
        $this->assertSame('Nile tilapia', $fish->common_name);
        $this->assertSame('Oreochromis niloticus', $fish->scientific_name);
        $this->assertSame('freshwater', $fish->water_type);
        $this->assertSame('https://www.fao.org/fishery/docs/CDrom/aquaculture/I1129m/file/en/en_niletilapia.htm', $fish->source_url);
        $this->assertStringContainsString('31-36 °C', $fish->habitat_requirements);
        $this->assertStringContainsString('not a lake-specific suitability threshold', $fish->habitat_requirements);
        $this->assertSame(['Pandin Lake', 'Sampaloc Lake', 'Yambo Lake'], $fish->waterBodies()->orderBy('name')->pluck('name')->all());
        foreach ($fish->waterBodies as $lake) {
            $this->assertSame('aquaculture', $lake->pivot->evidence_type);
            $this->assertSame('July-September 2017', $lake->pivot->sampling_period);
            $this->assertSame(2019, $lake->pivot->publication_year);
            $this->assertSame('insufficient_data', $lake->pivot->compatibility_status);
            $this->assertSame('https://link.springer.com/article/10.1007/s00128-019-02685-z', $lake->pivot->source_url);
        }
        $this->assertSame($originalLakes, WaterBody::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('pollution_reports', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_root_seeder_is_repeatable_across_time_and_preserves_unrelated_species(): void
    {
        $this->freezeTime();
        $this->seed(DatabaseSeeder::class);
        $originalFish = FishSpecies::sole()->toArray();
        $originalLinks = WaterBodyFish::orderBy('id')->get()->toArray();
        $unrelated = FishSpecies::forceCreate([
            'common_name' => 'Isolated fixture only', 'scientific_name' => 'Fixture species', 'water_type' => 'fixture',
        ])->fresh();
        $this->travel(1)->days();

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('fish_species', 2);
        $this->assertDatabaseCount('water_body_fish', 3);
        $this->assertSame($originalFish, FishSpecies::where('scientific_name', 'Oreochromis niloticus')->sole()->toArray());
        $this->assertSame($originalLinks, WaterBodyFish::orderBy('id')->get()->toArray());
        $this->assertSame($unrelated->toArray(), $unrelated->fresh()->toArray());
    }

    public function test_missing_lakes_refuse_seeding_without_partial_species_records(): void
    {
        try {
            (new FishSpeciesSeeder)->run();
            $this->fail('Required lake records must exist.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fish seeding requires exactly one existing record for each target lake.', $exception->getMessage());
            $this->assertDatabaseCount('fish_species', 0);
            $this->assertDatabaseCount('water_body_fish', 0);
        }
    }

    public function test_existing_duplicate_species_are_refused_instead_of_relinked_arbitrarily(): void
    {
        $this->seed(WaterBodySeeder::class);
        foreach ([1, 2] as $number) {
            FishSpecies::forceCreate(['scientific_name' => 'Oreochromis niloticus', 'common_name' => 'Duplicate test fixture '.$number, 'water_type' => 'fixture']);
        }

        try {
            (new FishSpeciesSeeder)->run();
            $this->fail('Duplicate scientific names require manual resolution.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Resolve duplicate Nile tilapia records before seeding.', $exception->getMessage());
            $this->assertDatabaseCount('fish_species', 2);
            $this->assertDatabaseCount('water_body_fish', 0);
        }
    }

    public function test_link_insert_failure_rolls_back_the_species_and_all_links(): void
    {
        $this->seed(WaterBodySeeder::class);
        $yambo = WaterBody::where('name', 'Yambo Lake')->sole();
        WaterBodyFish::creating(function (WaterBodyFish $link) use ($yambo): void {
            if ($link->water_body_id === $yambo->id) {
                throw new RuntimeException('Isolated link failure.');
            }
        });

        try {
            (new FishSpeciesSeeder)->run();
            $this->fail('The simulated failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Isolated link failure.', $exception->getMessage());
            $this->assertDatabaseCount('fish_species', 0);
            $this->assertDatabaseCount('water_body_fish', 0);
            $this->assertDatabaseCount('water_bodies', 3);
        }
    }

    public function test_refuses_an_unapproved_database_without_opening_a_connection(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'unapproved_day3_target']);
        DB::purge('mysql');
        $connection = DB::connection('mysql');
        $unopenedPdo = $connection->getRawPdo();

        try {
            (new FishSpeciesSeeder)->run();
            $this->fail('An unapproved target must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertSame('FishSpeciesSeeder requires the aquamap_laguna MySQL database.', $exception->getMessage());
            $this->assertSame($unopenedPdo, $connection->getRawPdo());
            $this->assertDatabaseCount('fish_species', 0, 'sqlite');
        }
    }

    public function test_refuses_an_unexpected_connected_database_before_writes(): void
    {
        $connection = Mockery::mock(MySqlConnection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('aquamap_laguna');
        $connection->shouldReceive('selectOne')->with('SELECT DATABASE() AS name', [], false)->once()
            ->andReturn((object) ['name' => 'unapproved_day3_target']);
        $connection->shouldNotReceive('transaction');
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->with(null)->andReturn($connection);
        $originalResolver = FishSpecies::getConnectionResolver();
        FishSpecies::setConnectionResolver($resolver);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FishSpeciesSeeder refused an unexpected connected database.');

        try {
            (new FishSpeciesSeeder)->run();
        } finally {
            FishSpecies::setConnectionResolver($originalResolver);
        }
    }

    public function test_refuses_sqlite_outside_testing(): void
    {
        $this->app->instance('env', 'local');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FishSpeciesSeeder requires the aquamap_laguna MySQL database.');

        (new FishSpeciesSeeder)->run();
    }
}
