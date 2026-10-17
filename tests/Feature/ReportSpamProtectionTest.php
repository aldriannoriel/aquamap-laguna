<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReportSpamProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->startOfSecond());
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    private function payload(): array
    {
        if (! WaterBody::exists()) {
            $this->seed(WaterBodySeeder::class);
        }
        $token = $this->get(route('reports.create'))->assertOk()->viewData('reportFormToken');

        return ['water_body_id' => WaterBody::where('name', 'Pandin Lake')->sole()->id,
            'pollution_type' => 'solid_waste', 'description' => 'Synthetic isolated spam protection fixture.', 'report_form_token' => $token];
    }

    private function readyPayload(): array
    {
        $payload = $this->payload();
        $this->travel(3)->seconds();

        return $payload;
    }

    public function test_minimum_time_is_enforced_at_millisecond_boundary_and_retry_keeps_original_timer(): void
    {
        $payload = $this->payload();
        $this->travel(2999)->milliseconds();
        $this->post(route('reports.store'), $payload + ['issued_at' => 0])->assertSessionHasErrors('report_form_token')
            ->assertSessionHasInput('description', $payload['description']);
        $this->assertDatabaseCount('pollution_reports', 0);
        $retry = $this->get(route('reports.create'))->assertOk();
        $this->assertSame($payload['report_form_token'], $retry->viewData('reportFormToken'));
        $this->travel(1)->milliseconds();
        $this->post(route('reports.store'), $payload)->assertSessionHasNoErrors()->assertSessionHas('report_received', true);
        $this->assertDatabaseCount('pollution_reports', 1);
        $this->assertSame('pending', PollutionReport::sole()->status);
    }

    #[TestWith([null])]
    #[TestWith(['not-authenticated-ciphertext'])]
    #[TestWith([['invalid-shape']])]
    public function test_missing_or_malformed_tokens_are_rejected_safely(mixed $value): void
    {
        $payload = $this->readyPayload();
        $payload['report_form_token'] = $value;
        $this->post(route('reports.store'), $payload)->assertSessionHasErrors('report_form_token');
        $this->get(route('reports.create'))->assertOk();
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_modified_ciphertext_and_oversized_tokens_are_rejected(): void
    {
        $payload = $this->readyPayload();
        foreach ([$payload['report_form_token'].'tampered', str_repeat('x', 2049)] as $token) {
            $this->post(route('reports.store'), array_replace($payload, ['report_form_token' => $token]))->assertSessionHasErrors('report_form_token');
        }
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_token_is_bound_to_the_anonymous_session_csrf_secret(): void
    {
        $payload = $this->readyPayload();
        session()->regenerateToken();
        $this->post(route('reports.store'), $payload)->assertSessionHasErrors('report_form_token');
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    public function test_expired_token_is_refreshed_without_losing_safe_input(): void
    {
        $payload = $this->payload();
        $this->travel(7200001)->milliseconds();
        $this->post(route('reports.store'), $payload)->assertSessionHasErrors('report_form_token');
        $retry = $this->get(route('reports.create'))->assertOk()->assertSee($payload['description']);
        $payload['report_form_token'] = $retry->viewData('reportFormToken');
        $this->travel(3)->seconds();
        $this->post(route('reports.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pollution_reports', 1);
    }

    public function test_authenticated_but_invalid_payload_shapes_purposes_and_future_dates_are_rejected(): void
    {
        $payload = $this->readyPayload();
        $valid = ['purpose' => 'pollution-report', 'issued_at' => now()->subMinute()->getTimestampMs(), 'session' => hash('sha256', session()->token())];
        $values = [[], array_replace($valid, ['purpose' => 'other']), array_replace($valid, ['issued_at' => now()->addMinute()->getTimestampMs()]), array_replace($valid, ['issued_at' => 'invalid'])];
        foreach ($values as $value) {
            $this->post(route('reports.store'), array_replace($payload, ['report_form_token' => Crypt::encryptString(json_encode($value))]))->assertSessionHasErrors('report_form_token');
        }
        $this->post(route('reports.store'), array_replace($payload, ['report_form_token' => Crypt::encryptString('not-json')]))->assertSessionHasErrors('report_form_token');
        $this->assertDatabaseCount('pollution_reports', 0);
    }

    #[TestWith(['https://bot.example.test'])]
    #[TestWith([0])]
    #[TestWith([['invalid']])]
    public function test_nonempty_or_invalid_honeypot_is_rejected_before_storing_photos(mixed $value): void
    {
        Storage::fake('pollution-reports');
        $payload = $this->readyPayload();
        $this->post(route('reports.store'), $payload + ['website' => $value, 'photo' => UploadedFile::fake()->image('bot.png')])
            ->assertSessionHasErrors('website');
        $this->assertDatabaseCount('pollution_reports', 0);
        $this->assertArrayNotHasKey('website', session()->getOldInput());
        $this->assertSame([], Storage::disk('pollution-reports')->allFiles());
    }

    #[TestWith([''])]
    #[TestWith([null])]
    public function test_blank_honeypot_and_server_timer_work_without_javascript(mixed $value): void
    {
        $this->post(route('reports.store'), $this->readyPayload() + ['website' => $value])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pollution_reports', 1);
    }

    public function test_recent_same_session_lake_and_type_get_a_warning_but_both_reports_are_saved_pending(): void
    {
        $payload = $this->readyPayload();
        $this->post(route('reports.store'), $payload)->assertSessionHas('possible_duplicate', false);
        $this->post(route('reports.store'), array_replace($payload, ['description' => 'A different legitimate observation.', 'status' => 'verified']))
            ->assertSessionHasNoErrors()->assertSessionHas('possible_duplicate', true);
        $this->assertDatabaseCount('pollution_reports', 2);
        $this->assertSame(['pending', 'pending'], PollutionReport::orderBy('id')->pluck('status')->all());
        $this->get(route('reports.create'))->assertSee('Possible repeat submission')->assertSee('still been saved privately')
            ->assertDontSee('A different legitimate observation.')->assertDontSee($payload['description']);
    }

    public function test_other_people_reports_and_client_supplied_receipt_ids_do_not_trigger_warnings_or_leak_content(): void
    {
        $payload = $this->readyPayload();
        $private = PollutionReport::forceCreate(['water_body_id' => $payload['water_body_id'], 'pollution_type' => $payload['pollution_type'],
            'description' => 'Other private fixture contents.', 'reporter_email' => 'other-private@example.test', 'status' => 'verified']);
        $this->post(route('reports.store'), $payload + ['recent_pollution_report_ids' => [$private->id]])
            ->assertSessionHas('possible_duplicate', false)->assertSessionHasNoErrors();
        $this->get(route('reports.create'))->assertDontSee('Other private fixture contents.')->assertDontSee('other-private@example.test')
            ->assertDontSee('Possible repeat submission');
        $this->assertSame('verified', $private->fresh()->status);
    }

    public function test_changing_lake_or_type_does_not_trigger_a_repeat_warning(): void
    {
        $payload = $this->readyPayload();
        $this->post(route('reports.store'), $payload)->assertSessionHasNoErrors();
        foreach ([['water_body_id' => WaterBody::where('name', 'Yambo Lake')->sole()->id], ['pollution_type' => 'other']] as $change) {
            $this->post(route('reports.store'), array_replace($payload, $change))->assertSessionHas('possible_duplicate', false);
        }
        $this->assertDatabaseCount('pollution_reports', 3);
    }

    public function test_old_receipts_expire_and_new_anonymous_sessions_do_not_inherit_history(): void
    {
        $payload = $this->readyPayload();
        $this->post(route('reports.store'), $payload)->assertSessionHasNoErrors();
        $oldId = PollutionReport::sole()->id;
        $this->travel(61)->minutes();
        $this->post(route('reports.store'), $payload)->assertSessionHas('possible_duplicate', false);
        $this->assertNotContains($oldId, session()->get('recent_pollution_report_ids'));
        $this->flushSession();
        $this->post(route('reports.store'), $this->readyPayload())->assertSessionHas('possible_duplicate', false);
        $this->assertDatabaseCount('pollution_reports', 3);
    }

    public function test_receipt_history_is_bounded_and_contains_only_ids(): void
    {
        $payload = $this->readyPayload();
        $ids = [];
        for ($i = 0; $i < 12; $i++) {
            $ids[] = PollutionReport::forceCreate(['water_body_id' => $payload['water_body_id'], 'pollution_type' => 'other', 'description' => 'Isolated fixture.'])->id;
        }
        $this->withSession(['recent_pollution_report_ids' => $ids])->post(route('reports.store'), $payload)->assertSessionHasNoErrors();
        $history = session()->get('recent_pollution_report_ids');
        $this->assertCount(10, $history);
        $this->assertSame(PollutionReport::latest('id')->first()->id, end($history));
        $this->assertSame([...array_slice($ids, -9), PollutionReport::latest('id')->first()->id], array_values($history));
        foreach ($history as $id) {
            $this->assertIsInt($id);
        }
    }
}
