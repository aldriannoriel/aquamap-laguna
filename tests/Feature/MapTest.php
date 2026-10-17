<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    private function lakePayload(TestResponse $response): array
    {
        $this->assertSame(1, preg_match('/<script id="lake-data" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches));

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_public_map_loads_the_three_seeded_lakes_with_only_public_fields(): void
    {
        $this->seed(WaterBodySeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        PollutionReport::forceCreate([
            'water_body_id' => $lake->id,
            'reporter_email' => 'private-fixture@example.test',
            'pollution_type' => 'test_fixture',
            'description' => 'Private test-only report.',
        ]);

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertViewIs('map.index')
            ->assertSee('San Pablo lake explorer')->assertDontSee('private-fixture@example.test')
            ->assertDontSee('Private test-only report.');
        $payload = $this->lakePayload($response);
        $this->assertSame(['Pandin Lake', 'Sampaloc Lake', 'Yambo Lake'], array_column($payload, 'name'));
        foreach ($payload as $item) {
            $this->assertSame(['id', 'name', 'municipality', 'water_type', 'latitude', 'longitude', 'description', 'source_url'], array_keys($item));
        }
        $this->assertSame($lake->latitude, $payload[1]['latitude']);
        $this->assertSame($lake->longitude, $payload[1]['longitude']);
        $this->assertSame($lake->source_url, $payload[1]['source_url']);
        $this->assertDatabaseCount('water_bodies', 3);
    }

    public function test_map_reads_current_database_values_and_excludes_other_lakes_and_localities(): void
    {
        $this->seed(WaterBodySeeder::class);
        $lake = WaterBody::where('name', 'Sampaloc Lake')->sole();
        $otherLocation = WaterBody::where('name', 'Pandin Lake')->sole();
        $lake->update(['latitude' => $otherLocation->latitude, 'description' => 'Changed only inside the isolated test database.']);
        $unrelated = $lake->replicate();
        $unrelated->name = 'Unrelated lake fixture';
        $unrelated->save();
        $differentLocality = $lake->replicate();
        $differentLocality->municipality = 'Other locality fixture';
        $differentLocality->save();

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertDontSee('Unrelated lake fixture')->assertDontSee('Other locality fixture');
        $payload = $this->lakePayload($response);
        $this->assertCount(3, $payload);
        $this->assertSame($otherLocation->latitude, $payload[1]['latitude']);
        $this->assertSame('Changed only inside the isolated test database.', $payload[1]['description']);
    }

    #[TestWith([null, null])]
    #[TestWith(['   ', ''])]
    #[TestWith([null, 'javascript:alert(1)'])]
    #[TestWith([null, 'data:text/html,<script>alert(1)</script>'])]
    #[TestWith([null, 'not a URL'])]
    public function test_missing_or_unsafe_optional_data_has_clear_fallbacks(?string $description, ?string $source): void
    {
        $this->seed(WaterBodySeeder::class);
        WaterBody::where('name', 'Pandin Lake')->update(['description' => $description, 'source_url' => $source]);

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertSee('No description is available for this lake.')
            ->assertSee('No research source is available for this lake.');
        $payload = $this->lakePayload($response);
        $this->assertNull($payload[0]['description']);
        $this->assertNull($payload[0]['source_url']);
    }

    public function test_database_text_is_escaped_in_html_and_cannot_break_the_json_script(): void
    {
        $this->seed(WaterBodySeeder::class);
        $description = '</script><script>window.fixtureXss = true</script>';
        WaterBody::where('name', 'Pandin Lake')->update([
            'description' => $description,
            'water_type' => '<img src=x onerror=alert(1)>',
        ]);

        $response = $this->get(route('map.index'));

        $response->assertOk()->assertSee($description)->assertDontSee($description, false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->assertSame($description, $this->lakePayload($response)[0]['description']);
    }

    public function test_an_empty_database_shows_an_empty_state_without_invented_coordinates(): void
    {
        $response = $this->get(route('map.index'));

        $response->assertOk()->assertSee('No lake records are available. Please check again later.');
        $this->assertSame([], $this->lakePayload($response));
        $this->assertDatabaseCount('water_bodies', 0);
    }
}
