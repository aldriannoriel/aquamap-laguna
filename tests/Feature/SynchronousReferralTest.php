<?php

namespace Tests\Feature;

use App\Jobs\SendReferral;
use App\Mail\EnvironmentalReferral;
use App\Models\Agency;
use App\Models\PollutionReport;
use App\Models\Referral;
use App\Models\User;
use App\Models\WaterBody;
use App\Support\MunicipalityRouting;
use App\Support\ReferralWorkflow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

class SynchronousReferralTest extends TestCase
{
    private function fixture(): array
    {
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Queue::fake();
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.username' => 'sender@example.test',
            'mail.mailers.smtp.password' => 'fixture-only', 'mail.from.address' => 'sender@example.test', 'referrals.mailer' => 'smtp']);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $report = PollutionReport::forceCreate(['water_body_id' => WaterBody::firstOrFail()->id, 'status' => 'pending',
            'pollution_type' => 'plastic_waste', 'description' => 'Synthetic observation', 'reporter_email' => 'private@example.test']);
        $agency = Agency::forceCreate(['name' => 'Synthetic recipient', 'municipality' => 'San Pablo City', 'email' => Referral::TEST_RECIPIENT,
            'active' => true, 'verification_status' => 'provisional', 'identity_key' => Agency::identityKey('Synthetic recipient', 'San Pablo City', null)]);
        MunicipalityRouting::select($report, $agency, $admin, 'Reviewed the incident evidence manually.', MunicipalityRouting::pointKey($report), 'pending');
        $this->actingAs($admin);

        return [$report, $agency, $admin];
    }

    private function send(PollutionReport $report, Agency $agency): TestResponse
    {
        return $this->post(route('admin.referrals.store', $report), ['agency_id' => $agency->id, 'include_photo' => 0,
            'review_hash' => ReferralWorkflow::reviewHash($report, $agency, false), 'approved' => 1]);
    }

    private function transport(?string $failure, PollutionReport $report): void
    {
        Mail::swap(new MailManager(app()));
        $transport = \Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('__toString')->andReturn('smtp');
        $transport->shouldReceive('send')->once()->andReturnUsing(function ($message, $envelope) use ($failure, $report): SentMessage {
            $this->assertSame('pending', $report->fresh()->status);
            $this->assertSame('sending', Referral::sole()->status);
            (new SendReferral(Referral::sole()->id))->handle();
            if ($failure !== null) {
                throw new TransportException($failure);
            }

            return new SentMessage($message, $envelope);
        });
        Mail::extend('smtp', fn () => $transport);
    }

    public function test_pending_actions_send_immediately_without_queue_dependency_or_duplicates(): void
    {
        [$report, $agency] = $this->fixture();
        config(['queue.connections.referrals.driver' => 'null']);
        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee('Send Email')->assertSee('Reject Report')
            ->assertSee('Email Information')->assertSee('Not Sent')->assertDontSee('name="referral_message"', false);
        $this->send($report, $agency)->assertRedirectToRoute('admin.reports.show', $report);
        $this->assertSame('accepted', Referral::sole()->status);
        $this->assertSame('forwarded', $report->fresh()->category);
        $this->assertNotSame('resolved', $report->fresh()->status);
        $this->send($report, $agency)->assertRedirect();
        Mail::assertSent(EnvironmentalReferral::class, 1);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_transport_acceptance_precedes_forwarding_even_with_interleaved_duplicate(): void
    {
        [$report, $agency] = $this->fixture();
        $this->transport(null, $report);
        $this->send($report, $agency)->assertRedirect();
        $referral = Referral::sole();
        $this->assertSame('accepted', $referral->status);
        $this->assertNotNull($referral->sent_at);
        $this->assertNotNull($referral->provider_message_id);
        $this->assertNull($referral->inbox_confirmed_at);
        $this->assertSame('forwarded', $report->fresh()->category);
        $this->assertStringNotContainsString('private@example.test', json_encode($referral->payload));
        Queue::assertNothingPushed();
    }

    public function test_failed_send_keeps_pending_and_explicit_retry_preserves_attempt_history(): void
    {
        [$report, $agency] = $this->fixture();
        $this->transport('Expected response code "235" but got code "535", with message "private diagnostics".', $report);
        $this->send($report, $agency)->assertRedirect();
        $referral = Referral::sole();
        $this->assertSame('pending', $report->fresh()->status);
        $this->assertSame('failed', $referral->status);
        $this->get(route('admin.reports.show', $report))->assertSee('Gmail rejected sender authentication')->assertDontSee('private diagnostics');
        Mail::fake();
        $retryAdmin = User::factory()->create();
        $retryAdmin->forceFill(['is_admin' => true])->save();
        $this->actingAs($retryAdmin);
        $this->post(route('admin.referrals.retry', [$report, $referral]), ['approved' => 1])->assertRedirect();
        $this->post(route('admin.referrals.retry', [$report, $referral]), ['approved' => 1])->assertConflict();
        $this->assertSame('forwarded', $report->fresh()->category);
        $this->assertSame(2, $referral->fresh()->attempts);
        $this->assertSame(1, $referral->events()->where('event', 'smtp_auth_rejected')->count());
        $this->assertSame(1, $referral->events()->where('event', 'admin_safe_retry_requested_by_admin_'.$retryAdmin->id)->count());
        $this->assertSame($retryAdmin->id, $report->reviews()->reorder('id', 'desc')->first()->administrator_id);
        Mail::assertSent(EnvironmentalReferral::class, 1);
        Queue::assertNothingPushed();
    }

    public function test_unknown_transport_outcome_blocks_retry_without_forwarding(): void
    {
        [$report, $agency] = $this->fixture();
        $this->transport('Connection lost after submission; sensitive provider detail.', $report);
        $this->send($report, $agency)->assertRedirect();
        $this->assertSame('pending', $report->fresh()->status);
        $this->assertSame('uncertain', Referral::sole()->status);
        $this->post(route('admin.referrals.retry', [$report, Referral::sole()]), ['approved' => 1])->assertConflict();
        $this->get(route('admin.reports.show', $report))->assertSee('Failed')->assertSee('outcome is uncertain')->assertDontSee('sensitive provider detail');
        Queue::assertNothingPushed();
    }

    public function test_invalid_recipient_blocks_sending(): void
    {
        [$report, $agency] = $this->fixture();
        $agency->forceFill(['email' => 'invalid-recipient'])->save();
        $this->send($report, $agency)->assertSessionHasErrors('agency_id');
        $this->assertDatabaseCount('referrals', 0);
        $this->assertSame('pending', $report->fresh()->status);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_previously_approved_jobs_stay_paused_until_explicit_admin_confirmation(): void
    {
        [$report, $agency, $admin] = $this->fixture();
        $referral = ReferralWorkflow::enqueue($report, $agency, $admin, false, ReferralWorkflow::reviewHash($report, $agency, false));
        $job = new SendReferral($referral->id);
        $queued = \Mockery::mock(Job::class);
        $queued->shouldReceive('release')->once()->with(86400);
        $job->setJob($queued);
        $job->handle();
        $this->assertSame('queued', $referral->fresh()->status);
        $this->assertSame(0, $referral->fresh()->attempts);
        Mail::assertNothingSent();
        $this->post(route('admin.referrals.store', $report), ['agency_id' => $agency->id, 'include_photo' => 0,
            'review_hash' => ReferralWorkflow::existingHash($referral), 'approved' => 1])->assertRedirect();
        $this->assertSame('accepted', $referral->fresh()->status);
        Mail::assertSent(EnvironmentalReferral::class, 1);
    }

    public function test_municipality_updates_require_notes_and_preserve_delivery_and_report_status(): void
    {
        [$report, $agency] = $this->fixture();
        $data = ['status' => 'municipality_update', 'expected_status' => 'pending', 'review_note' => 'Response received.'];
        $this->patch(route('admin.reports.status', $report), $data)->assertSessionHasErrors('status');
        $this->send($report, $agency)->assertRedirect();
        $before = DB::table('referrals')->get()->map(fn (object $row): array => (array) $row)->all();
        $count = $report->reviews()->count();
        $this->patch(route('admin.reports.status', $report), ['status' => 'municipality_update', 'expected_status' => 'verified'])->assertSessionHasErrors('review_note');
        $this->patch(route('admin.reports.status', $report), [...$data, 'expected_status' => 'verified'])->assertRedirect();
        $this->assertSame($count + 1, $report->reviews()->count());
        $this->assertStringContainsString('Municipality update:', $report->reviews()->reorder('id', 'desc')->first()->review_note);
        $this->assertSame('forwarded', $report->fresh()->category);
        $this->assertSame($before, DB::table('referrals')->get()->map(fn (object $row): array => (array) $row)->all());
        Mail::assertSent(EnvironmentalReferral::class, 1);
    }

    public function test_stalled_submission_can_be_audited_without_sending_cancelling_or_claiming_acceptance(): void
    {
        [$report, $agency, $admin] = $this->fixture();
        $this->freezeTime();
        $referral = ReferralWorkflow::enqueue($report, $agency, $admin, false, ReferralWorkflow::reviewHash($report, $agency, false));
        $referral->forceFill(['status' => 'sending', 'attempts' => 1, 'sending_at' => now()->subMinutes(6), 'updated_at' => now()->subMinutes(6)])->save();
        $before = $referral->fresh()->getAttributes();
        $reportBefore = $report->fresh()->getAttributes();
        $events = $referral->events()->count();
        $this->getJson(route('admin.referrals.status', $report))->assertOk()
            ->assertJson(['status' => 'sending', 'label' => 'Delivery outcome unknown', 'interrupted' => true]);
        $this->assertSame($before, $referral->fresh()->getAttributes());
        $url = route('admin.referrals.reconcile', [$report, $referral]);
        $this->post($url)->assertSessionHasErrors('approved');
        $this->post($url, ['approved' => 1])->assertRedirectToRoute('admin.reports.show', $report);
        $referral->refresh();
        $this->assertSame('uncertain', $referral->status);
        $this->assertSame('smtp_request_interrupted', $referral->error_code);
        $this->assertNotNull($referral->failed_at);
        $this->assertNull($referral->sent_at);
        $this->assertFalse($referral->retryable());
        $this->assertFalse($referral->cancellable());
        $this->assertSame($events + 1, $referral->events()->count());
        $this->assertSame('interrupted_delivery_reconciled_by_admin_'.$admin->id, $referral->events()->reorder('id', 'desc')->first()->event);
        $this->assertSame($before, array_replace($referral->getAttributes(), array_intersect_key($before, array_flip(['status', 'error_code', 'failed_at', 'updated_at']))));
        $this->assertSame($reportBefore, $report->fresh()->getAttributes());
        $this->post($url, ['approved' => 1])->assertConflict();
        $this->post(route('admin.referrals.retry', [$report, $referral]), ['approved' => 1])->assertConflict();
        $this->assertSame($events + 1, $referral->events()->count());
        Mail::assertNothingSent();
    }

    public function test_reconciliation_blocks_guests_non_admins_wrong_reports_active_and_accepted_deliveries(): void
    {
        [$report, $agency, $admin] = $this->fixture();
        $this->freezeTime();
        $referral = ReferralWorkflow::enqueue($report, $agency, $admin, false, ReferralWorkflow::reviewHash($report, $agency, false));
        $referral->forceFill(['status' => 'sending', 'sending_at' => now()])->save();
        $url = route('admin.referrals.reconcile', [$report, $referral]);
        $this->post($url, ['approved' => 1])->assertConflict();
        $this->actingAs(User::factory()->create())->post($url, ['approved' => 1])->assertForbidden();
        auth()->logout();
        $this->post($url, ['approved' => 1])->assertRedirectToRoute('admin.login');
        $other = PollutionReport::forceCreate(['water_body_id' => $report->water_body_id, 'status' => 'pending',
            'pollution_type' => 'plastic_waste', 'description' => 'Other isolated report']);
        $this->actingAs($admin)->post(route('admin.referrals.reconcile', [$other, $referral]), ['approved' => 1])->assertNotFound();
        $referral->forceFill(['status' => 'accepted', 'sent_at' => now(), 'updated_at' => now()->subMinutes(6)])->save();
        $before = $referral->fresh()->getAttributes();
        $this->post($url, ['approved' => 1])->assertConflict();
        $this->assertSame($before, $referral->fresh()->getAttributes());
        Mail::assertNothingSent();
    }

    public function test_interrupted_preparation_remains_safely_retryable_without_starting_smtp(): void
    {
        [$report, $agency, $admin] = $this->fixture();
        $this->freezeTime();
        $referral = ReferralWorkflow::enqueue($report, $agency, $admin, false, ReferralWorkflow::reviewHash($report, $agency, false));
        $referral->forceFill(['status' => 'preparing', 'updated_at' => now()->subMinutes(6)])->save();
        $this->post(route('admin.referrals.reconcile', [$report, $referral]), ['approved' => 1])->assertRedirect();
        $referral->refresh();
        $this->assertSame('failed', $referral->status);
        $this->assertSame('preflight_interrupted', $referral->error_code);
        $this->assertTrue($referral->retryable());
        $this->assertTrue($referral->cancellable());
        $this->assertSame('verified', $report->fresh()->status);
        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee('Preparation was interrupted before SMTP submission started.')
            ->assertSee('Retry safely')->assertDontSee('The delivery outcome is uncertain.');
        Mail::assertNothingSent();
    }

    public function test_late_transport_acknowledgement_can_finish_a_reconciled_attempt_without_duplicate_sending(): void
    {
        [$report, $agency, $admin] = $this->fixture();
        $this->freezeTime();
        Mail::swap(new MailManager(app()));
        $transport = \Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('__toString')->andReturn('smtp');
        $transport->shouldReceive('send')->once()->andReturnUsing(function ($message, $envelope) use ($admin): SentMessage {
            $referral = Referral::sole();
            $this->travel(6)->minutes();
            ReferralWorkflow::reconcileInterrupted($referral, $admin);
            $this->assertSame('uncertain', $referral->fresh()->status);

            return new SentMessage($message, $envelope);
        });
        Mail::extend('smtp', fn () => $transport);
        $this->send($report, $agency)->assertRedirect();
        $this->assertSame('accepted', Referral::sole()->status);
        $this->assertNotNull(Referral::sole()->sent_at);
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertNotSame('resolved', $report->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_synchronous_send_extends_a_short_php_limit_without_changing_smtp_credentials(): void
    {
        [$report, $agency] = $this->fixture();
        $previous = (int) ini_get('max_execution_time');
        $mailer = config('mail.mailers.smtp');
        try {
            set_time_limit(30);
            $this->send($report, $agency)->assertRedirect();
            $this->assertGreaterThanOrEqual(120, (int) ini_get('max_execution_time'));
            $this->assertSame('accepted', Referral::sole()->status);
            $this->assertSame($mailer, config('mail.mailers.smtp'));
            Mail::assertSent(EnvironmentalReferral::class, 1);
            Queue::assertNothingPushed();
        } finally {
            set_time_limit($previous);
        }
    }

    public function test_reconciled_legacy_approval_is_not_acceptance_and_cannot_be_retried_confirmed_or_resolved(): void
    {
        [$report, $agency, $admin] = $this->fixture();
        $this->freezeTime();
        $referral = ReferralWorkflow::enqueue($report, $agency, $admin, false, ReferralWorkflow::reviewHash($report, $agency, false));
        ReferralWorkflow::record($referral, 'municipality_delivery_confirmed_by_admin_'.$admin->id);
        $referral->forceFill(['status' => 'uncertain', 'attempts' => 1, 'sending_at' => now()->subMinutes(6),
            'failed_at' => now(), 'error_code' => 'smtp_request_interrupted'])->save();
        ReferralWorkflow::record($referral, 'interrupted_delivery_reconciled_by_admin_'.$admin->id);
        $reportBefore = $report->fresh()->getAttributes();
        $deliveryBefore = $referral->fresh()->getAttributes();
        $eventsBefore = $referral->events()->get()->map->getAttributes()->all();
        $reviewsBefore = $report->reviews()->get()->map->getAttributes()->all();

        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee($referral->reference)
            ->assertSee('No matching inbox message is not proof of failed delivery.')
            ->assertSee('Follow-up controls require recorded SMTP acceptance')->assertSee('Mark as Resolved')->assertSee('Close Case')
            ->assertSee('Supporting Evidence')->assertDontSee('Reconcile interrupted delivery')->assertDontSee('Retry safely')->assertDontSee('Reject Report');
        $this->assertFalse($report->fresh()->forwarded());
        $this->assertSame([], $report->fresh()->availableTransitions());
        $this->post(route('admin.referrals.retry', [$report, $referral]), ['approved' => 1])->assertConflict();
        $this->post(route('admin.referrals.reconcile', [$report, $referral]), ['approved' => 1])->assertConflict();
        $this->post(route('admin.referrals.confirm', [$report, $referral]), ['approved' => 1,
            'confirmation_reference' => $referral->reference])->assertStatus(422);
        foreach (['under_review', 'in_progress', 'municipality_update', 'resolved', 'closed', 'rejected'] as $target) {
            $this->patch(route('admin.reports.status', $report), ['status' => $target, 'expected_status' => 'verified',
                'review_note' => 'Reviewed legacy report.', 'closure_reason' => 'Administrative closure.', 'rejection_reason' => 'Initial rejection.',
                ...($target === 'resolved' ? ['supporting_evidence' => [UploadedFile::fake()->image('confirmation.png')], 'resolution_confirmed' => 1] : [])])
                ->assertSessionHasErrors('status');
        }
        $this->assertSame($reportBefore, $report->fresh()->getAttributes());
        $this->assertSame($deliveryBefore, $referral->fresh()->getAttributes());
        $this->assertSame($eventsBefore, $referral->events()->get()->map->getAttributes()->all());
        $this->assertSame($reviewsBefore, $report->reviews()->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('report_evidence', 0);
        Mail::assertNothingSent();
    }

    public function test_new_report_sends_normally_without_changing_an_uncertain_legacy_report(): void
    {
        [$legacy, $agency, $admin] = $this->fixture();
        $referral = ReferralWorkflow::enqueue($legacy, $agency, $admin, false, ReferralWorkflow::reviewHash($legacy, $agency, false));
        $referral->forceFill(['status' => 'uncertain', 'attempts' => 1, 'sending_at' => now()->subMinutes(6),
            'failed_at' => now(), 'error_code' => 'smtp_request_interrupted'])->save();
        $legacyBefore = $legacy->fresh()->getAttributes();
        $deliveryBefore = $referral->fresh()->getAttributes();
        $eventsBefore = $referral->events()->get()->map->getAttributes()->all();
        $reviewsBefore = $legacy->reviews()->get()->map->getAttributes()->all();
        Queue::fake();

        $new = PollutionReport::forceCreate(['water_body_id' => $legacy->water_body_id, 'status' => 'pending',
            'pollution_type' => 'plastic_waste', 'description' => 'Separate new synthetic observation']);
        MunicipalityRouting::select($new, $agency, $admin, 'Reviewed the new incident location.', MunicipalityRouting::pointKey($new), 'pending');
        $this->send($new, $agency)->assertRedirectToRoute('admin.reports.show', $new);
        $accepted = $new->fresh()->referral;
        $this->assertSame('accepted', $accepted->status);
        $this->assertNotNull($accepted->sent_at);
        $this->assertTrue($accepted->events()->where('event', 'transport_accepted')->exists());
        $this->assertSame('forwarded', $new->fresh()->category);
        $this->assertContains('resolved', $new->fresh()->availableTransitions());
        $this->assertNotSame('resolved', $new->fresh()->status);
        $this->get(route('admin.reports.show', $new))->assertOk()->assertSee('Mark as Resolved')
            ->assertDontSee('Follow-up controls require recorded SMTP acceptance');
        $this->assertSame($legacyBefore, $legacy->fresh()->getAttributes());
        $this->assertSame($deliveryBefore, $referral->fresh()->getAttributes());
        $this->assertSame($eventsBefore, $referral->events()->get()->map->getAttributes()->all());
        $this->assertSame($reviewsBefore, $legacy->reviews()->get()->map->getAttributes()->all());
        Mail::assertSent(EnvironmentalReferral::class, 1);
        Queue::assertNothingPushed();
    }
}
