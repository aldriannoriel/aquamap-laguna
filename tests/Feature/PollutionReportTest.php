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

    private function payload(bool $withPhoto = true, string $email = 'citizen@example.test'): array
    {
        $this->seed(WaterBodySeeder::class);
        $token = $this->get(route('reports.create'))->assertOk()->viewData('reportFormToken');
        $this->travel(3)->seconds();
        $this->verifyEmailSession($email);

        if ($withPhoto) {
            Storage::fake('pollution-reports');
        }

        return [
            ...($withPhoto ? ['photo' => UploadedFile::fake()->image('proof.png')] : []),
            'water_body_id' => WaterBody::where('name', 'Pandin Lake')->sole()->id,
            'pollution_type' => 'solid_waste',
            'description' => 'Synthetic isolated test observation; never a real report.',
            'report_form_token' => $token,
            'reporter_email' => $email,
        ];
    }

    public function test_form_and_map_navigation_load(): void
    {
        $this->seed(WaterBodySeeder::class);
        $this->get(route('reports.create'))->assertOk()->assertViewIs('reports.create')
            ->assertSee('Sampaloc Lake')->assertSee('Pandin Lake')->assertSee('Yambo Lake')
            ->assertSee('name="_token"', false)->assertSee('No incident location selected.')
            ->assertSee('positional precision remains unconfirmed')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('map.index'))->assertOk()->assertSee(route('reports.create'));
    }

    #[TestWith(['plastic_waste'])]
    #[TestWith(['floating_garbage'])]
    #[TestWith(['illegal_dumping'])]
    #[TestWith(['solid_waste'])]
    #[TestWith(['wastewater'])]
    #[TestWith(['oil_chemical'])]
    #[TestWith(['fish_death'])]
    #[TestWith(['water_change'])]
    #[TestWith(['other'])]
    public function test_visible_and_existing_categories_are_saved_unchanged_as_pending(string $type): void
    {
        $payload = array_replace($this->payload(), ['pollution_type' => $type, 'status' => 'verified']);

        $this->post(route('reports.store'), $payload)->assertRedirectToRoute('reports.create')->assertSessionHasNoErrors();

        $report = PollutionReport::sole();
        $this->assertSame($type, $report->pollution_type);
        $this->assertSame('pending', $report->status);
        $this->assertSame($payload['description'], $report->description);
        $this->assertDatabaseCount('report_reviews', 0);
        $response = $this->get(route('map.index'))->assertOk();
        $this->assertArrayNotHasKey('verified_report_count', collect($response->viewData('lakes'))->firstWhere('id', $report->water_body_id));
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
        foreach (['latitude', 'longitude', 'reporter_name'] as $field) {
            $this->assertNull($report->$field);
        }
        $this->assertSame($payload['reporter_email'], $report->reporter_email);
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
        $payload = array_replace($this->payload(false, 'private-test@example.test'), [
            'photo' => UploadedFile::fake()->image('private-observation.'.$extension),
            'reporter_name' => 'Private Test Reporter',
            'latitude' => '14.1000000',
            'longitude' => '121.3000000',
        ]);
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
            'water_body_id' => 'Please choose a water body.', 'pollution_type' => 'Please choose an observation type.',
            'description' => 'Please describe what you observed.', 'reporter_email' => 'Please enter your email address to receive a verification code.']);
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_empty_lake_catalog_explains_unavailability_and_prevents_submission(): void
    {
        $this->get(route('reports.create'))->assertOk()->assertSee('No water body records are available.')
            ->assertSee('id="submit-report"', false)->assertSee('disabled', false);
        $this->post(route('reports.store'), ['water_body_id' => 1, 'pollution_type' => 'other', 'description' => 'Isolated fixture.'])
            ->assertSessionHasErrors(['water_body_id' => 'Please choose a listed water body in the selected category.']);
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
        $this->post(route('reports.store'), $this->payload(false) + ['photo' => UploadedFile::fake()->image('boundary.png')->size(5120)])
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
        $this->post(route('reports.store'), $this->payload(false) + ['photo' => $upload])
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
        $this->post(route('reports.store'), array_replace($payload, ['photo' => $disguised]))->assertSessionHasErrors('photo');
        $this->post(route('reports.store'), array_replace($payload, ['photo' => UploadedFile::fake()->image('large.png')->size(5121)]))
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
        $this->assertNotNull($report->photo_path);
        $this->assertStringNotContainsString('unsafe.php', $report->photo_path);
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
        for ($i = 0; $i < 5; $i++) {
            $payload = $this->payload(true, "citizen{$i}@example.test");
            $this->post(route('reports.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        }
        $payload = $this->payload(true, 'citizen_extra@example.test');
        $this->post(route('reports.store'), $payload)->assertStatus(429)->assertSee('Please try again later.')->assertHeader('Retry-After');
        $this->assertDatabaseCount('pollution_reports', 5);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post(route('reports.store'), $this->payload(true, 'citizen_remote@example.test'))->assertRedirect();
        $this->assertDatabaseCount('pollution_reports', 6);
        $this->travel(61)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->post(route('reports.store'), $this->payload(true, 'citizen_after_cooldown@example.test'))->assertRedirect();
        $this->assertDatabaseCount('pollution_reports', 7);
    }

    public function test_unverified_emails_cannot_submit_reports(): void
    {
        $payload = $this->payload();
        $payload['reporter_email'] = 'unverified-person@example.test';

        $this->post(route('reports.store'), $payload)
            ->assertRedirectToRoute('reports.create')
            ->assertSessionHasErrors(['reporter_email' => 'Please verify your email with a valid OTP before submitting.']);

        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_email_verification_expires_after_thirty_minutes(): void
    {
        $email = 'expiring-citizen@example.test';
        $payload = $this->payload(true, $email);

        $this->travel(31)->minutes();

        $this->post(route('reports.store'), $payload)
            ->assertRedirectToRoute('reports.create')
            ->assertSessionHasErrors(['reporter_email' => 'Please verify your email with a valid OTP before submitting.']);

        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_successfully_submitted_report_enforces_five_hour_email_cooldown_across_sessions(): void
    {
        $email = 'cooldown-citizen@example.test';

        // Session 1: Submit report successfully
        $payload1 = $this->payload(true, $email);
        $this->post(route('reports.store'), $payload1)
            ->assertRedirectToRoute('reports.create')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pollution_reports', 1);

        // Session 2: Clear cookies to simulate a new browser session
        $this->unencryptedCookies = [];
        $payload2 = $this->payload(true, $email);

        // Try submitting within 5 hours (1 hour later)
        $this->travel(1)->hours();
        $this->post(route('reports.store'), $payload2)
            ->assertRedirectToRoute('reports.create')
            ->assertSessionHasErrors(['reporter_email' => 'This email has already submitted a report. Please wait 5 hours before submitting another.']);

        $this->assertDatabaseCount('pollution_reports', 1);

        // After 5 hours (5 hours and 2 minutes from the original report)
        $this->travel(4)->hours();
        $this->travel(2)->minutes();
        $payload3 = $this->payload(true, $email);
        $this->post(route('reports.store'), $payload3)
            ->assertRedirectToRoute('reports.create')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pollution_reports', 2);
    }

    public function test_failed_save_removes_uploaded_photo_and_returns_safe_error(): void
    {
        Storage::fake('pollution-reports');
        $payload = $this->payload();
        PollutionReport::creating(function (): void {
            throw new \RuntimeException('Sensitive database failure fixture');
        });
        try {
            $this->post(route('reports.store'), array_replace($payload, ['photo' => UploadedFile::fake()->image('fixture.png')]))
                ->assertRedirectToRoute('reports.create')->assertSessionHasErrors('submission')->assertDontSee('Sensitive database failure fixture');
            $this->assertSame([], Storage::disk('pollution-reports')->allFiles());
            $this->assertDatabaseCount('pollution_reports', 0);
        } finally {
            PollutionReport::flushEventListeners();
        }
    }
}
