<?php

namespace Tests\Feature;

use App\Jobs\SendReferral;
use App\Mail\EnvironmentalReferral;
use App\Models\Agency;
use App\Models\PollutionReport;
use App\Models\Referral;
use App\Models\User;
use App\Models\WaterBody;
use App\Support\ReferralWorkflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Approve &amp; Send', false)->assertDontSee('private@example.test')->assertDontSee('<script>unsafe()</script>', false);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $data = ['agency_id' => $a->id, 'include_photo' => 1, 'review_hash' => ReferralWorkflow::reviewHash($r, $a, true), 'approved' => 1];
        $this->post(route('admin.referrals.store', $r), $data)->assertRedirectToRoute('admin.referrals.index');
        $this->post(route('admin.referrals.store', $r), $data)->assertRedirectToRoute('admin.referrals.index');
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

    public function test_stale_preview_inactive_or_wrong_recipient_and_unverified_reports_are_rejected(): void
    {
        $r = $this->report();
        $a = $this->agency($r);
        $this->actingAs($this->admin());
        $hash = ReferralWorkflow::reviewHash($r, $a, false);
        $r->forceFill(['description' => 'Changed since preview'])->save();
        $data = ['agency_id' => $a->id, 'include_photo' => 0, 'review_hash' => $hash, 'approved' => 1];
        $this->post(route('admin.referrals.store', $r), $data)->assertConflict();
        $r->forceFill(['status' => 'pending'])->save();
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
        $this->assertDatabaseCount('referral_events', 1);
        $this->assertDatabaseCount('pollution_reports', 1);
    }

    public function test_mail_configuration_blocks_log_transports_and_test_send_requires_explicit_approval(): void
    {
        $this->actingAs($this->admin());
        $page = $this->get(route('admin.referrals.index'))->assertOk();
        Mail::assertNothingSent();
        $this->post(route('admin.referrals.test'), ['request_token' => $page->viewData('testToken')])->assertSessionHasErrors('approved');
        config(['referrals.mailer' => 'log']);
        $this->post(route('admin.referrals.test'), ['request_token' => $page->viewData('testToken'), 'approved' => 1])->assertSessionHasErrors('delivery');
        $this->assertDatabaseCount('referrals', 0);
        Queue::assertNothingPushed();
    }

    public function test_live_test_is_deduplicated_and_receipt_requires_matching_reference(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        $page = $this->get(route('admin.referrals.index'));
        $token = $page->viewData('testToken');
        $this->post(route('admin.referrals.test'), ['request_token' => $token, 'approved' => 1])->assertRedirect();
        $this->post(route('admin.referrals.test'), ['request_token' => $token, 'approved' => 1])->assertStatus(419);
        $ref = Referral::sole();
        $this->assertNull($ref->pollution_report_id);
        $this->assertSame(Referral::TEST_RECIPIENT, $ref->recipient_snapshot['email']);
        (new SendReferral($ref->id))->handle();
        $this->post(route('admin.referrals.confirm', $ref), ['approved' => 1, 'confirmation_reference' => 'wrong'])->assertUnprocessable();
        $this->post(route('admin.referrals.confirm', $ref), ['approved' => 1, 'confirmation_reference' => $ref->reference])->assertRedirect();
        $this->assertNotNull($ref->fresh()->inbox_confirmed_at);
        Queue::assertPushed(SendReferral::class, 1);
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
        $this->actingAs($this->admin())->post(route('admin.referrals.retry', $ref), ['approved' => 1])->assertConflict();
    }
}
