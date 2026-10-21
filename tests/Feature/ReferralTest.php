<?php

namespace Tests\Feature;

use App\Jobs\SendReferral;
use App\Mail\EnvironmentalReferral;
use App\Models\Agency;
use App\Models\PollutionReport;
use App\Models\Referral;
use App\Models\ReportReview;
use App\Models\User;
use App\Models\WaterBody;
use App\Support\ReferralWorkflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        Storage::fake('pollution-reports');
        Mail::fake();
        Queue::fake();
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'fake-secret',
            'mail.from.address' => 'sender@example.test', 'referrals.mailer' => 'smtp']);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    private function report(): PollutionReport
    {
        $path = UploadedFile::fake()->image('proof.jpg')->store('', ['disk' => 'pollution-reports']);

        return PollutionReport::forceCreate(['water_body_id' => WaterBody::where('name', 'Pandin Lake')->sole()->id, 'pollution_type' => 'plastic_waste',
            'description' => 'Community observation <script>unsafe()</script>', 'status' => 'verified', 'latitude' => 14.116, 'longitude' => 121.367,
            'landmark' => 'Reporter supplied landmark', 'observed_on' => '2026-01-01', 'photo_path' => $path, 'reporter_email' => 'private@example.test']);
    }

    private function agency(PollutionReport $r): Agency
    {
        $a = Agency::forceCreate(['name' => 'Provisional test office', 'municipality' => 'San Pablo City', 'email' => Referral::TEST_RECIPIENT,
            'active' => true, 'verification_status' => 'provisional', 'identity_key' => Agency::identityKey('Test office', 'San Pablo City', null)]);
        $a->waterBodies()->attach($r->water_body_id);

        return $a;
    }

    private function approve(PollutionReport $r, Agency $a, bool $photo = false): Referral
    {
        return ReferralWorkflow::enqueue($r, $a, $this->admin(), $photo, ReferralWorkflow::reviewHash($r, $a, $photo));
    }

    public function test_preview_and_approval_are_private_explicit_and_deduplicated_with_safe_attachment(): void
    {
        $r = $this->report();
        $a = $this->agency($r);
        $u = $this->admin();
        $this->get(route('admin.referrals.preview', $r))->assertRedirectToRoute('admin.login');
        $this->actingAs(User::factory()->create())->post(route('admin.referrals.store', $r))->assertForbidden();
        $this->actingAs($u)->get(route('admin.referrals.preview', [$r, 'agency_id' => $a->id, 'include_photo' => 1]))->assertOk()
            ->assertSee('Send to Municipality', false)->assertDontSee('private@example.test')->assertDontSee('<script>unsafe()</script>', false);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $data = ['agency_id' => $a->id, 'include_photo' => 1, 'review_hash' => ReferralWorkflow::reviewHash($r, $a, true), 'approved' => 1];
        $this->post(route('admin.referrals.store', $r), $data)->assertRedirectToRoute('admin.reports.show', $r);
        $this->post(route('admin.referrals.store', $r), $data)->assertRedirectToRoute('admin.reports.show', $r);
        $this->assertDatabaseCount('referrals', 1);
        Queue::assertPushed(SendReferral::class, 1);
        $ref = Referral::sole();
        $this->assertSame($u->id, $ref->approved_by);
        $this->assertStringNotContainsString('private@example.test', json_encode($ref->payload));
        (new SendReferral($ref->id))->handle();
        (new SendReferral($ref->id))->handle();
        Mail::assertSent(EnvironmentalReferral::class, 1);
        Mail::assertSent(EnvironmentalReferral::class, fn ($mail) => $mail->hasTo(Referral::TEST_RECIPIENT) && count($mail->attachments()) === 1);
        $this->assertSame('accepted', $ref->fresh()->status);
        $this->assertNull($ref->fresh()->inbox_confirmed_at);
        $this->assertSame('verified', $r->fresh()->status);
        $raw = DB::table('referrals')->value('payload');
        $this->assertStringNotContainsString('Reporter supplied landmark', $raw);
        $html = (new EnvironmentalReferral($ref->payload, $ref->reference))->render();
        $this->assertStringContainsString('google.com/maps/search/', $html);
        $this->assertStringContainsString('not an independently verified environmental violation', $html);
        $safe = ReferralWorkflow::attachment($ref->payload);
        $this->assertSame('image/jpeg', getimagesizefromstring($safe)['mime']);
    }

    public function test_historical_unsupported_report_remains_readable_without_a_send_form(): void
    {
        $report = $this->report();
        $this->agency($report);
        DB::table('water_bodies')->where('id', $report->water_body_id)->update(['category' => 'Spring']);

        $this->actingAs($this->admin())->get(route('admin.reports.show', $report))->assertOk()
            ->assertSee($report->description)->assertDontSee('id="municipality-send-form"', false);
        $this->assertDatabaseCount('pollution_reports', 1);
        $this->assertDatabaseCount('referrals', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_stale_preview_inactive_or_wrong_recipient_and_unverified_reports_are_rejected(): void
    {
        $r = $this->report();
        $a = $this->agency($r);
        $this->actingAs($this->admin());
        $hash = ReferralWorkflow::reviewHash($r, $a, false);
        $r->forceFill(['description' => 'Changed since preview'])->save();
        $data = ['agency_id' => $a->id, 'include_photo' => 0, 'review_hash' => $hash, 'approved' => 1];
        $this->post(route('admin.referrals.store', $r), $data)->assertConflict();
        $r->forceFill(['status' => 'rejected'])->save();
        $this->post(route('admin.referrals.store', $r), $data)->assertUnprocessable();
        $a->forceFill(['active' => false])->save();
        $this->get(route('admin.referrals.preview', [$r, 'agency_id' => $a->id]))->assertUnprocessable();
        $this->assertDatabaseCount('referrals', 0);
        Mail::assertNothingSent();
    }

    public function test_deleted_directory_entry_keeps_snapshot_and_history(): void
    {
        $r = $this->report();
        $a = $this->agency($r);
        $ref = $this->approve($r, $a);
        $this->actingAs($this->admin())->delete(route('admin.agencies.destroy', $a), ['confirm_delete' => 1])->assertRedirect();
        $this->assertNull($ref->fresh()->agency_id);
        $this->assertSame(Referral::TEST_RECIPIENT, $ref->fresh()->recipient_snapshot['email']);
        $this->assertDatabaseCount('referral_events', 2);
        $this->assertDatabaseCount('pollution_reports', 1);
    }

    public function test_removed_testing_routes_are_unavailable_and_bad_transport_cannot_send(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/referrals')->assertNotFound();
        $this->post('/admin/referrals/test', ['approved' => 1])->assertNotFound();
        $r = $this->report();
        $a = $this->agency($r);
        $this->get(route('admin.reports.show', $r))->assertOk()->assertDontSee('Record decision')->assertDontSee('Referrals &amp; email test', false)->assertSee('Send to Municipality');
        config(['referrals.mailer' => 'log']);
        $this->post(route('admin.referrals.store', $r), ['agency_id' => $a->id, 'include_photo' => 0, 'review_hash' => ReferralWorkflow::reviewHash($r, $a, false), 'approved' => 1])->assertSessionHasErrors('delivery');
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_receiver_receipt_requires_an_accepted_real_referral_and_exact_reference(): void
    {
        $r = $this->report();
        $a = $this->agency($r);
        $ref = $this->approve($r, $a);
        $this->actingAs($this->admin());
        $url = route('admin.referrals.confirm', [$r, $ref]);
        $this->post($url, ['approved' => 1, 'confirmation_reference' => $ref->reference])->assertUnprocessable();
        (new SendReferral($ref->id))->handle();
        $this->post($url, ['approved' => 1, 'confirmation_reference' => 'wrong'])->assertUnprocessable();
        $this->post($url, ['approved' => 1, 'confirmation_reference' => $ref->reference])->assertRedirect();
        $this->post($url, ['approved' => 1, 'confirmation_reference' => $ref->reference])->assertRedirect();
        $this->assertNotNull($ref->fresh()->inbox_confirmed_at);
        $this->assertSame(1, $ref->events()->where('event', 'inbox_receipt_confirmed_by_administrator')->count());
        $this->post(route('admin.referrals.store', $r), ['agency_id' => $a->id, 'include_photo' => 0,
            'review_hash' => ReferralWorkflow::reviewHash($r, $a, false), 'approved' => 1])
            ->assertSessionHas('referral_saved', 'Referral email sent successfully (accepted by SMTP). Receiver inbox receipt was separately confirmed.');
        Mail::assertSent(EnvironmentalReferral::class, 1);
    }

    public function test_preflight_failure_can_be_retried_but_ambiguous_transport_is_never_resent(): void
    {
        $r = $this->report();
        $a = $this->agency($r);
        $ref = $this->approve($r, $a);
        config(['mail.mailers.smtp.password' => null]);
        (new SendReferral($ref->id))->handle();
        $this->assertSame('failed', $ref->fresh()->status);
        $this->assertNull($ref->fresh()->sending_at);
        Mail::assertNothingSent();
        config(['mail.mailers.smtp.password' => 'fake-secret']);
        ReferralWorkflow::retry($ref);
        Mail::swap(new MailManager(app()));
        $transport = \Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow(new \RuntimeException('secret transport credentials must never be logged'));
        $transport->shouldReceive('__toString')->andReturn('smtp');
        Mail::extend('smtp', fn () => $transport);
        (new SendReferral($ref->id))->handle();
        (new SendReferral($ref->id))->handle();
        $this->assertSame('uncertain', $ref->fresh()->status);
        $this->assertSame('transport_outcome_unknown', $ref->fresh()->error_code);
        $this->assertStringNotContainsString('secret transport', json_encode($ref->fresh()->getAttributes()));
        $this->actingAs($this->admin())->post(route('admin.referrals.retry', [$r, $ref]), ['approved' => 1])->assertConflict();
    }

    public function test_moderation_does_not_send_and_smtp_handoff_moves_report_out_of_action_required_without_losing_evidence(): void
    {
        $r = $this->report();
        $r->forceFill(['status' => 'pending'])->save();
        $a = $this->agency($r);
        $this->actingAs($this->admin())->patch(route('admin.reports.status', $r), ['expected_status' => 'pending', 'status' => 'verified', 'review_note' => 'Reviewed for referral'])->assertRedirect();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $r->refresh();
        $photo = $r->photo_path;
        $this->get(route('admin.reports.show', $r))->assertOk()->assertSee('Your decision was recorded');
        $data = ['agency_id' => $a->id, 'include_photo' => 0, 'review_hash' => ReferralWorkflow::reviewHash($r, $a, false), 'approved' => 1];
        $this->post(route('admin.referrals.store', $r), $data)->assertSessionHas('referral_saved', 'Referral queued for sending. Waiting for the municipality worker.');
        $this->get(route('admin.reports.index', ['queue' => 'action']))->assertSee('Queued');
        $ref = Referral::sole();
        (new SendReferral($ref->id))->handle();
        $this->get(route('admin.reports.index', ['queue' => 'action']))->assertDontSee('Report #'.$r->id);
        $this->get(route('admin.reports.index', ['queue' => 'processed']))->assertSee('Report #'.$r->id)->assertSee('Sent / accepted by SMTP');
        $this->get(route('admin.reports.show', $r))->assertOk()->assertSee('Sent / accepted by SMTP')->assertSee('Report status:')->assertSee('Report follow-up');
        $this->get(route('admin.reports.show', $r))->assertSee('Referral email sent successfully.');
        $this->post(route('admin.referrals.store', $r), $data)->assertSessionHas('referral_saved', 'Referral email sent successfully (accepted by SMTP). Inbox receipt is unverified.');
        (new SendReferral($ref->id))->handle();
        Queue::assertPushed(SendReferral::class, 1);
        Mail::assertSent(EnvironmentalReferral::class, 1);
        $this->assertDatabaseCount('report_reviews', 1);
        $this->assertSame($photo, $r->fresh()->photo_path);
        Storage::disk('pollution-reports')->assertExists($photo);
        $this->assertSame('14.1160000', $r->fresh()->latitude);
    }

    public function test_one_municipality_routes_multiple_bodies_and_unknown_or_boundary_locality_never_selects_a_default(): void
    {
        $r = $this->report();
        $a = $this->agency($r);
        $a->waterBodies()->detach();
        $other = WaterBody::where('name', 'Yambo Lake')->sole();
        $this->actingAs($this->admin());
        $this->get(route('admin.reports.show', $r))->assertViewHas('suggestedRecipient', $a->id);
        $this->get(route('admin.referrals.preview', [$r, 'agency_id' => $a->id]))->assertOk();
        $r->forceFill(['water_body_id' => $other->id])->save();
        $this->get(route('admin.reports.show', $r))->assertViewHas('suggestedRecipient', $a->id);
        foreach (['Unknown', 'Majayjay / Liliw access area'] as $locality) {
            $other->forceFill(['municipality' => $locality])->save();
            $this->get(route('admin.reports.show', $r))->assertViewHas('suggestedRecipient', null)->assertSee('Select and verify the recipient manually.');
            $this->get(route('admin.referrals.preview', $r))->assertSessionHasErrors('agency_id');
            $this->get(route('admin.referrals.preview', [$r, 'agency_id' => $a->id]))->assertOk();
        }
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_connection_failure_before_submission_permits_safe_retry_without_exposing_diagnostics(): void
    {
        $report = $this->report();
        $referral = $this->approve($report, $this->agency($report));
        Mail::swap(new MailManager(app()));
        $transport = \Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow(new TransportException('Connection could not be established with host private-provider-details'));
        $transport->shouldReceive('__toString')->andReturn('smtp');
        Mail::extend('smtp', fn () => $transport);

        (new SendReferral($referral->id))->handle();

        $this->assertSame('failed', $referral->fresh()->status);
        $this->assertSame('smtp_connection_failed', $referral->fresh()->error_code);
        $this->actingAs($this->admin())->get(route('admin.reports.show', $report))->assertOk()
            ->assertSee('SMTP connection or TLS failed before submission.')->assertSee('Retry safely')->assertDontSee('private-provider-details');
        Mail::fake();
        $this->post(route('admin.referrals.retry', [$report, $referral]), ['approved' => 1])->assertRedirect();
        (new SendReferral($referral->id))->handle();
        (new SendReferral($referral->id))->handle();
        $this->assertSame('accepted', $referral->fresh()->status);
        $this->assertSame(2, $referral->fresh()->attempts);
        Mail::assertSent(EnvironmentalReferral::class, 1);
    }

    public function test_explicit_smtp_rejection_is_visible_retryable_and_keeps_attempt_history(): void
    {
        $r = $this->report();
        $ref = $this->approve($r, $this->agency($r));
        Mail::swap(new MailManager(app()));
        $transport = \Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow(new TransportException('Expected response code "235" but got code "535", with message "private provider diagnostics".'));
        $transport->shouldReceive('__toString')->andReturn('smtp');
        Mail::extend('smtp', fn () => $transport);
        (new SendReferral($ref->id))->handle();
        $this->assertSame('failed', $ref->fresh()->status);
        $this->assertSame('smtp_auth_rejected', $ref->fresh()->error_code);
        $this->actingAs($this->admin())->get(route('admin.reports.show', $r))->assertSee('Gmail rejected sender authentication.')->assertSee('Retry safely')->assertDontSee('private provider diagnostics');
        $this->get(route('admin.reports.index', ['queue' => 'action']))->assertSee('Report #'.$r->id)->assertSee('Failed');
        Mail::fake();
        $this->post(route('admin.referrals.retry', [$r, $ref]), ['approved' => 1])->assertRedirect();
        $this->post(route('admin.referrals.retry', [$r, $ref]), ['approved' => 1])->assertConflict();
        (new SendReferral($ref->id))->handle();
        $this->assertSame(2, $ref->fresh()->attempts);
        $this->assertSame('accepted', $ref->fresh()->status);
        $this->assertSame(1, $ref->events()->where('event', 'smtp_auth_rejected')->count());
        Mail::assertSent(EnvironmentalReferral::class, 1);
    }

    public function test_pending_report_is_approved_and_queued_atomically_by_one_explicit_send(): void
    {
        $r = $this->report();
        $r->forceFill(['status' => 'pending'])->save();
        $a = $this->agency($r);
        $this->actingAs($this->admin());
        $data = ['agency_id' => $a->id, 'include_photo' => 0, 'review_hash' => ReferralWorkflow::reviewHash($r, $a, false)];
        $this->post(route('admin.referrals.store', $r), $data)->assertSessionHasErrors('approved');
        $this->assertSame('pending', $r->fresh()->status);
        $this->post(route('admin.referrals.store', $r), [...$data, 'approved' => 1])->assertRedirectToRoute('admin.reports.show', $r);
        $this->post(route('admin.referrals.store', $r), [...$data, 'approved' => 1])->assertRedirect();
        $this->assertSame('verified', $r->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 1);
        $this->assertSame('queued', Referral::sole()->status);
        Queue::assertPushed(SendReferral::class, fn ($job) => $job->connection === 'referrals' && $job->queue === 'municipality-referrals');
        Queue::assertPushed(SendReferral::class, 1);
        Mail::assertNothingSent();
        $this->assertContains('php artisan queue:work referrals --queue=municipality-referrals --tries=3 --timeout=45', array_column(DevCommands::commands(), 'command'));
    }

    public function test_five_legacy_jobs_are_preserved_and_only_explicit_report_reapproval_can_deliver(): void
    {
        $r = $this->report();
        $a = $this->agency($r);
        $u = $this->admin();
        $legacy = Referral::forceCreate(['reference' => fake()->uuid(), 'dedupe_key' => 'report:'.$r->id, 'pollution_report_id' => $r->id,
            'agency_id' => $a->id, 'approved_by' => $u->id, 'approved_at' => now(), 'queued_at' => now(), 'status' => 'queued',
            'payload' => ReferralWorkflow::payload($r, false), 'recipient_snapshot' => ReferralWorkflow::recipient($a), 'include_photo' => false]);
        $records = [$legacy];
        for ($i = 0; $i < 4; $i++) {
            $records[] = Referral::forceCreate(['reference' => fake()->uuid(), 'dedupe_key' => 'legacy-diagnostic:'.$i,
                'approved_by' => $u->id, 'approved_at' => now(), 'queued_at' => now(), 'status' => 'queued',
                'payload' => ['test' => true, 'description' => 'Legacy diagnostic fixture'], 'recipient_snapshot' => ['name' => 'Legacy diagnostic', 'email' => Referral::TEST_RECIPIENT], 'include_photo' => false]);
        }
        foreach ($records as $record) {
            DB::table('jobs')->insert(['queue' => 'referrals', 'payload' => json_encode(['referralId' => $record->id]), 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()]);
            (new SendReferral($record->id))->handle();
        }
        $jobs = DB::table('jobs')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('jobs', 5);
        $this->actingAs($u)->get(route('admin.reports.show', $r))->assertOk()->assertSee('legacy approval is paused');
        $a->forceFill(['email' => 'changed@example.test'])->save();
        $this->get(route('admin.referrals.preview', [$r, 'agency_id' => $a->id]))->assertOk()
            ->assertSee(Referral::TEST_RECIPIENT)->assertSee($legacy->reference)->assertDontSee('changed@example.test');
        $data = ['agency_id' => $a->id, 'include_photo' => 0, 'review_hash' => ReferralWorkflow::existingHash($legacy), 'approved' => 1];
        $this->post(route('admin.referrals.store', $r), $data)->assertRedirect();
        $this->post(route('admin.referrals.store', $r), $data)->assertRedirect();
        Queue::assertPushed(SendReferral::class, 1);
        (new SendReferral($legacy->id))->handle();
        (new SendReferral($legacy->id))->handle();
        Mail::assertSent(EnvironmentalReferral::class, 1);
        $this->assertSame('accepted', $legacy->fresh()->status);
        $this->assertSame(4, Referral::whereNull('pollution_report_id')->where('status', 'queued')->count());
        $this->assertSame($jobs, DB::table('jobs')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
    }

    #[TestWith(['queued'])]
    #[TestWith(['preparing'])]
    #[TestWith(['failed'])]
    public function test_rejection_requires_a_reason_and_cancels_a_safely_unsent_referral(string $state): void
    {
        $report = $this->report();
        $referral = $this->approve($report, $this->agency($report));
        $referral->forceFill(['status' => $state, 'claim_token' => 'old-claim',
            'error_code' => $state === 'failed' ? 'smtp_rejected' : null,
            'sending_at' => $state === 'failed' ? now() : null])->save();
        $snapshot = $referral->only(['payload', 'recipient_snapshot', 'approved_at', 'queued_at', 'sending_at', 'attempts']);
        $admin = $this->admin();
        $url = route('admin.reports.status', $report);
        $this->actingAs($admin)->patch($url, ['status' => 'rejected', 'expected_status' => 'verified', 'rejection_reason' => '   '])
            ->assertSessionHasErrors(['rejection_reason' => 'Explain why this report is being rejected.']);
        $this->assertSame($state, $referral->fresh()->status);
        $this->patch($url, ['status' => 'rejected', 'expected_status' => 'verified', 'rejection_reason' => 'Duplicate of documented case FIXTURE-04.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('rejected', $report->fresh()->status);
        $this->assertSame('cancelled', $referral->fresh()->status);
        $this->assertEquals($snapshot, $referral->fresh()->only(array_keys($snapshot)));
        $this->assertSame(1, $referral->events()->where('event', 'cancelled_report_rejected_by_admin_'.$admin->id)->count());
        (new SendReferral($referral->id))->handle();
        $this->post(route('admin.referrals.retry', [$report, $referral]), ['approved' => 1])->assertConflict();
        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseCount('report_reviews', 1);
        $this->get(route('admin.reports.index', ['queue' => 'action']))
            ->assertViewHas('reports', fn ($reports) => $reports->isEmpty());
        $this->get(route('admin.reports.index', ['queue' => 'processed']))
            ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === [$report->id]);
        Mail::assertNothingSent();
    }

    #[TestWith(['sending'])]
    #[TestWith(['uncertain'])]
    #[TestWith(['accepted'])]
    public function test_rejection_is_blocked_after_submission_begins_or_is_accepted(string $state): void
    {
        $report = $this->report();
        $referral = $this->approve($report, $this->agency($report));
        $referral->forceFill(['status' => $state, 'sending_at' => now(), 'sent_at' => $state === 'accepted' ? now() : null])->save();
        $before = $referral->fresh()->getAttributes();
        $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), ['status' => 'rejected',
            'expected_status' => 'verified', 'rejection_reason' => 'Must not race SMTP.'])->assertSessionHasErrors('status');
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertSame($before, $referral->fresh()->getAttributes());
        $this->assertDatabaseCount('report_reviews', 0);
        Mail::assertNothingSent();
    }

    public function test_audit_failure_rolls_back_referral_cancellation_and_rejection_together(): void
    {
        $report = $this->report();
        $referral = $this->approve($report, $this->agency($report));
        $before = $referral->fresh()->getAttributes();
        ReportReview::creating(function (): void {
            throw new \RuntimeException('Isolated rejection audit failure.');
        });
        try {
            $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), ['status' => 'rejected',
                'expected_status' => 'verified', 'rejection_reason' => 'Isolated rollback fixture.'])->assertSessionHasErrors('status');
            $this->assertSame('verified', $report->fresh()->status);
            $this->assertSame($before, $referral->fresh()->getAttributes());
            $this->assertDatabaseCount('referral_events', 2);
            $this->assertDatabaseCount('report_reviews', 0);
            Mail::assertNothingSent();
        } finally {
            ReportReview::flushEventListeners();
        }
    }

    public function test_rejection_does_not_send_and_worker_interruption_keeps_a_retryable_audit(): void
    {
        $r = $this->report();
        $r->forceFill(['status' => 'pending'])->save();
        $this->actingAs($this->admin())->patch(route('admin.reports.status', $r), ['expected_status' => 'pending', 'status' => 'rejected', 'rejection_reason' => 'Duplicate isolated observation.'])->assertRedirect();
        $this->assertSame('rejected', $r->fresh()->status);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $r2 = $this->report();
        $ref = $this->approve($r2, $this->agency($r2));
        (new SendReferral($ref->id))->failed(new \RuntimeException('private failure details'));
        $this->assertSame('failed', $ref->fresh()->status);
        $this->assertTrue($ref->fresh()->retryable());
        $this->assertSame(1, $ref->events()->where('event', 'worker_interrupted')->count());
    }
}
