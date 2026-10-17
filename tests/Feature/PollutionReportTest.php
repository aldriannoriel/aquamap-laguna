<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PollutionReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    private function payload(): array
    {
        $this->seed(WaterBodySeeder::class);
        $token = $this->get(route('reports.create'))->assertOk()->viewData('reportFormToken');
        $this->travel(3)->seconds();

        return ['water_body_id' => WaterBody::where('name', 'Pandin Lake')->sole()->id,
            'pollution_type' => 'solid_waste', 'description' => 'Synthetic isolated test observation; never a real report.', 'report_form_token' => $token];
    }

    public function test_form_and_map_navigation_load(): void
    {
        $this->seed(WaterBodySeeder::class);
        $this->get(route('reports.create'))->assertOk()->assertViewIs('reports.create')
            ->assertSee('Sampaloc Lake')->assertSee('Pandin Lake')->assertSee('Yambo Lake')
            ->assertSee('name="_token"', false)->assertSee('No incident location selected.')
            ->assertSee('datum and precision remain unconfirmed')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('map.index'))->assertOk()->assertSee(route('reports.create'));
    }

    public function test_anonymous_report_is_pending_without_automatically_assigning_lake_coordinates(): void
    {
        $payload = $this->payload();
        $this->post(route('reports.store'), $payload)->assertRedirectToRoute('reports.create')
            ->assertSessionHas('report_received', true)->assertSessionHasNoErrors();
        $report = PollutionReport::sole();
        $this->assertSame($payload['water_body_id'], $report->water_body_id);
        $this->assertSame('pending', $report->status);
        $this->assertSame($payload['description'], $report->description);
        foreach (['latitude', 'longitude', 'reporter_name', 'reporter_email', 'photo_path'] as $field) {
            $this->assertNull($report->$field);
        }
        $this->assertTrue($report->waterBody->pollutionReports->contains($report));
        $this->get(route('reports.create'))->assertOk()->assertSee('Your report has been received.')
            ->assertDontSee($payload['description']);
    }

    #[TestWith(['jpeg'])]
    #[TestWith(['png'])]
    #[TestWith(['webp'])]
    public function test_optional_photo_contacts_and_incident_point_are_private(string $extension): void
    {
        Storage::fake('pollution-reports');
        $payload = $this->payload() + ['photo' => UploadedFile::fake()->image('private-observation.'.$extension),
            'reporter_name' => 'Private Test Reporter', 'reporter_email' => 'private-test@example.test',
            'latitude' => '14.1000000', 'longitude' => '121.3000000'];
        $this->post(route('reports.store'), $payload)->assertRedirectToRoute('reports.create')->assertSessionHasNoErrors();
        $report = PollutionReport::sole();
        $this->assertSame('14.1000000', $report->latitude);
        $this->assertSame('121.3000000', $report->longitude);
        $this->assertSame('Private Test Reporter', $report->reporter_name);
        $this->assertSame('private-test@example.test', $report->reporter_email);
        Storage::disk('pollution-reports')->assertExists($report->photo_path);
        $this->assertSame('private', config('filesystems.disks.pollution-reports.visibility'));
        $this->assertStringNotContainsString('private-observation', $report->photo_path);
        foreach (['reporter_name', 'reporter_email', 'photo_path'] as $field) {
            $this->assertArrayNotHasKey($field, $report->toArray());
        }
        foreach (['map.index', 'reports.create'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('private-test@example.test')->assertDontSee('Private Test Reporter')
                ->assertDontSee($report->photo_path)->assertDontSee($payload['description']);
        }
        $this->get('/reports/'.$report->id)->assertNotFound();
        $this->get('/storage/'.$report->photo_path)->assertForbidden();
        $this->get('/storage/pollution-reports/'.$report->photo_path)->assertForbidden();
        $this->assertFalse(config('filesystems.disks.pollution-reports.serve'));
    }

    public function test_required_fields_are_validated(): void
    {
        $this->post(route('reports.store'), [])->assertRedirectToRoute('reports.create')->assertSessionHasErrors([
            'water_body_id' => 'Please choose a lake.', 'pollution_type' => 'Please choose an observation type.',
            'description' => 'Please describe what you observed.']);
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_empty_lake_catalog_explains_unavailability_and_prevents_submission(): void
    {
        $this->get(route('reports.create'))->assertOk()->assertSee('No lake records are available.')
            ->assertSee('id="submit-report"', false)->assertSee('disabled', false);
        $this->post(route('reports.store'), ['water_body_id' => 1, 'pollution_type' => 'other', 'description' => 'Isolated fixture.'])
            ->assertSessionHasErrors(['water_body_id' => 'Please choose an existing lake.']);
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_json_validation_returns_errors_without_echoing_contact_information(): void
    {
        $this->postJson(route('reports.store'), ['reporter_email' => 'private@example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['water_body_id', 'pollution_type', 'description'])
            ->assertDontSee('private@example.test');
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_photo_at_five_megabyte_boundary_is_accepted(): void
    {
        Storage::fake('pollution-reports');
        $this->post(route('reports.store'), $this->payload() + ['photo' => UploadedFile::fake()->image('boundary.png')->size(5120)])
            ->assertRedirectToRoute('reports.create')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pollution_reports', 1);
        Storage::disk('pollution-reports')->assertExists(PollutionReport::sole()->photo_path);
    }

    #[TestWith(['water_body_id', 999999])]
    #[TestWith(['water_body_id', 'invalid'])]
    #[TestWith(['pollution_type', 'invented_type'])]
    #[TestWith(['pollution_type', ['solid_waste']])]
    #[TestWith(['description', '   '])]
    #[TestWith(['description', ['unsafe-shape']])]
    #[TestWith(['reporter_email', 'invalid-email'])]
    #[TestWith(['reporter_name', ['unsafe-shape']])]
    #[TestWith(['latitude', 91])]
    #[TestWith(['longitude', -181])]
    #[TestWith(['latitude', 'not-a-number'])]
    public function test_invalid_values_are_rejected_without_saving(string $field, mixed $value): void
    {
        $payload = array_replace($this->payload(), [$field => $value]);
        if ($field === 'latitude') {
            $payload['longitude'] = 121;
        }
        if ($field === 'longitude') {
            $payload['latitude'] = 14;
        }
        $this->post(route('reports.store'), $payload)->assertSessionHasErrors($field);
        $this->assertDatabaseCount('pollution_reports', 0);
        $this->get(route('reports.create'))->assertOk();
    }

    #[TestWith(['latitude', 14, 'longitude'])]
    #[TestWith(['longitude', 121, 'latitude'])]
    public function test_coordinates_must_be_a_pair(string $field, int $value, string $missing): void
    {
        $this->post(route('reports.store'), $this->payload() + [$field => $value])->assertSessionHasErrors($missing);
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    #[TestWith(['description', 5001])]
    #[TestWith(['reporter_name', 256])]
    #[TestWith(['reporter_email', 255])]
    public function test_text_length_limits(string $field, int $length): void
    {
        $this->post(route('reports.store'), array_replace($this->payload(), [$field => str_repeat('a', $length)]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    #[TestWith(['script.php', '<?php echo "unsafe";'])]
    #[TestWith(['fake.png', 'not an image'])]
    #[TestWith(['vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'])]
    public function test_unsafe_uploads_are_rejected(string $name, string $contents): void
    {
        Storage::fake('pollution-reports');
        $fixture = UploadedFile::fake()->createWithContent($name, $contents);
        // Use real MIME detection: fake files otherwise infer MIME from the filename.
        $upload = new UploadedFile($fixture->getRealPath(), $name, null, UPLOAD_ERR_OK, true);
        $this->post(route('reports.store'), $this->payload() + ['photo' => $upload])
            ->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('pollution_reports', 0);
        $this->assertSame([], Storage::disk('pollution-reports')->allFiles());
    }

    public function test_executable_extension_and_oversized_image_are_rejected(): void
    {
        Storage::fake('pollution-reports');
        $payload = $this->payload();
        $image = UploadedFile::fake()->image('real.png');
        $disguised = UploadedFile::fake()->createWithContent('real.php', file_get_contents($image->getRealPath()));
        $this->post(route('reports.store'), $payload + ['photo' => $disguised])->assertSessionHasErrors('photo');
        $this->post(route('reports.store'), $payload + ['photo' => UploadedFile::fake()->image('large.png')->size(5121)])
            ->assertSessionHasErrors(['photo' => 'The photo must be no larger than 5 MB.']);
        $this->assertDatabaseCount('pollution_reports', 0);
        $this->assertSame([], Storage::disk('pollution-reports')->allFiles());
    }

    public function test_client_status_paths_identifiers_and_timestamps_are_ignored(): void
    {
        $this->post(route('reports.store'), $this->payload() + [
            'status' => 'approved', 'photo_path' => '../../public/unsafe.php', 'id' => 9999, 'created_at' => '2001-01-01',
        ])->assertSessionHasNoErrors();
        $report = PollutionReport::sole();
        $this->assertSame('pending', $report->status);
        $this->assertNull($report->photo_path);
        $this->assertNotSame(9999, $report->id);
        $this->assertNotSame('2001-01-01', $report->created_at->toDateString());
    }

    public function test_validation_preserves_only_scalar_form_fields_and_escapes_them(): void
    {
        $payload = $this->payload();
        $payload['pollution_type'] = 'invalid';
        $payload['description'] = '<script>window.fixtureXss=true</script>';
        $this->post(route('reports.store'), $payload + [
            'reporter_name' => '"><script>window.fixtureXss=true</script>', 'latitude' => '14.1000000', 'longitude' => '121.3000000',
            'status' => 'approved', 'unknown' => 'never-flash',
        ])->assertSessionHasErrors('pollution_type')->assertSessionHasInput('description', $payload['description']);
        $this->assertArrayNotHasKey('unknown', session()->getOldInput());
        $this->assertArrayNotHasKey('status', session()->getOldInput());
        $this->get(route('reports.create'))->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>window.fixtureXss=true</script>', false)->assertSee('value="14.1000000"', false)->assertDontSee('never-flash');
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_sixth_attempt_is_limited_and_limit_expires_and_is_per_address(): void
    {
        $this->freezeTime();
        $payload = $this->payload();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('reports.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post(route('reports.store'), $payload)->assertStatus(429)->assertSee('Please try again later.')->assertHeader('Retry-After');
        $this->assertDatabaseCount('pollution_reports', 5);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post(route('reports.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('pollution_reports', 6);
        $this->travel(61)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->post(route('reports.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('pollution_reports', 7);
    }

    public function test_failed_save_removes_uploaded_photo_and_returns_safe_error(): void
    {
        Storage::fake('pollution-reports');
        $payload = $this->payload();
        PollutionReport::creating(function (): void {
            throw new \RuntimeException('Sensitive database failure fixture');
        });
        try {
            $this->post(route('reports.store'), $payload + ['photo' => UploadedFile::fake()->image('fixture.png')])
                ->assertRedirectToRoute('reports.create')->assertSessionHasErrors('submission')->assertDontSee('Sensitive database failure fixture');
            $this->assertSame([], Storage::disk('pollution-reports')->allFiles());
            $this->assertDatabaseCount('pollution_reports', 0);
        } finally {
            PollutionReport::flushEventListeners();
        }
    }
}
