<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReportLocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('pollution-reports');
    }

    private function payload(): array
    {
        $form = $this->get(route('reports.create'))->assertOk();
        $this->travel(3)->seconds();

        return ['water_body_id' => WaterBody::firstOrFail()->id, 'pollution_type' => 'other', 'description' => 'Private location fixture',
            'photo' => UploadedFile::fake()->image('proof.png'), 'report_form_token' => $form->viewData('reportFormToken')];
    }

    public function test_location_landmark_accuracy_and_observation_date_are_private_and_preserved(): void
    {
        $this->post(route('reports.store'), [...$this->payload(), 'latitude' => '14.1234567', 'longitude' => '121.2345678', 'landmark' => 'Private nearby landmark',
            'location_accuracy_m' => '7.25', 'observed_on' => '2026-01-01'])->assertSessionHasNoErrors();
        $r = PollutionReport::sole();
        $this->assertSame('14.1234567', $r->latitude);
        $this->assertSame('121.2345678', $r->longitude);
        $this->assertSame('7.25', $r->location_accuracy_m);
        $this->assertSame('Private nearby landmark', $r->landmark);
        $this->assertSame('2026-01-01', $r->observed_on->format('Y-m-d'));
        $this->assertSame('pending', $r->status);
        $this->get(route('map.index'))->assertOk()->assertDontSee('Private nearby landmark')->assertDontSee('14.1234567');
        foreach (['latitude', 'longitude', 'landmark', 'location_accuracy_m'] as $key) {
            $this->assertArrayNotHasKey($key, $r->toArray());
        }
        $this->get(route('reports.create'))->assertSee('Use My Current Location')->assertSee('HTTPS or localhost')->assertSee('incident-coordinates');
    }

    #[TestWith(['latitude', 91])]
    #[TestWith(['longitude', 181])]
    #[TestWith(['location_accuracy_m', -1])]
    #[TestWith(['observed_on', '2999-01-01'])]
    #[TestWith(['landmark', ['invalid']])]
    public function test_invalid_private_location_metadata_is_rejected(string $field, mixed $value): void
    {
        $this->postJson(route('reports.store'), [...$this->payload(), 'latitude' => 14.1, 'longitude' => 121.2, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_location_remains_optional_and_required_photo_is_preserved(): void
    {
        $data = $this->payload();
        $this->post(route('reports.store'), $data)->assertSessionHasNoErrors();
        $this->assertNull(PollutionReport::sole()->latitude);
        unset($data['photo']);
        $this->postJson(route('reports.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertDatabaseCount('pollution_reports', 1);
    }
}
