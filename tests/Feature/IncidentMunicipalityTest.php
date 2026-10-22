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
use App\Support\MunicipalityRouting;
use App\Support\ReferralWorkflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExplorerSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IncidentMunicipalityTest extends TestCase
{
    private function fixture(): array
    {
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->seed(DatabaseSeeder::class);
        $this->seed(ExplorerSeeder::class);
        Mail::fake();
        Queue::fake();
        config(['referrals.mailer' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'fixture-only',
            'mail.from.address' => 'sender@example.test']);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $water = WaterBody::where('name', 'Balanac River')->first() ?? WaterBody::where('category', 'river')->firstOrFail();
        $water->forceFill(['name' => 'Balanac River', 'municipality' => 'Pagsanjan'])->save();
        $report = PollutionReport::forceCreate(['id' => 4, 'water_body_id' => $water->id, 'status' => 'pending',
            'pollution_type' => 'solid_waste', 'description' => 'Synthetic incident routing fixture, not an actual report.',
            'latitude' => 14.19, 'longitude' => 121.43]);

        return [$report, $admin];
    }

    private function agency(string $municipality): Agency
    {
        return Agency::forceCreate(['name' => MunicipalityRouting::label($municipality), 'municipality' => $municipality,
            'email' => Referral::TEST_RECIPIENT, 'active' => true, 'verification_status' => 'provisional']);
    }

    private function selection(PollutionReport $report, Agency $agency, string $reason = 'Reviewed saved incident coordinates and supporting municipal information.'): array
    {
        return ['agency_id' => $agency->id, 'routing_reason' => $reason, 'point_key' => MunicipalityRouting::pointKey($report),
            'expected_status' => $report->status];
    }

    public function test_combined_review_records_manual_routing_and_confirms_once_without_sending_on_preview_or_cancellation(): void
    {
        [$report, $admin] = $this->fixture();
        $magdalena = $this->agency('Magdalena');
        $original = $report->fresh()->getAttributes();
        $url = route('admin.referrals.review', $report);
        $data = [...$this->selection($report, $magdalena), 'include_photo' => 0];
        $this->postJson($url, $data)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($url, $data)->assertForbidden();
        $this->actingAs($admin)->postJson($url, [...$data, 'routing_reason' => '   '])->assertUnprocessable()->assertJsonValidationErrors('routing_reason');
        $this->postJson($url, [...$data, 'point_key' => str_repeat('0', 64)])->assertConflict();
        $preview = $this->postJson($url, $data)->assertOk()->assertJsonPath('recipient', 'Magdalena / '.Referral::TEST_RECIPIENT);
        $this->assertStringContainsString('Incident municipality (admin selected): Magdalena', $preview->json('text'));
        $this->postJson($url, $data)->assertOk()->assertJsonPath('hash', $preview->json('hash'));
        $this->assertDatabaseCount('report_reviews', 1);
        $this->assertSame($original, $report->fresh()->getAttributes());
        $this->assertDatabaseCount('referrals', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        $approval = ['agency_id' => $magdalena->id, 'include_photo' => 0, 'review_hash' => $preview->json('hash'), 'approved' => 1];
        $this->post(route('admin.referrals.store', $report), $approval)->assertSessionHasNoErrors();
        $this->post(route('admin.referrals.store', $report), $approval)->assertSessionHasNoErrors();
        $this->assertSame('forwarded', $report->fresh()->category);
        $this->assertSame('accepted', Referral::sole()->status);
        Queue::assertNothingPushed();
        (new SendReferral(Referral::sole()->id))->handle();
        $this->assertSame('forwarded', $report->fresh()->category);
        $this->assertSame('verified', $report->fresh()->status);
        Mail::assertSent(EnvironmentalReferral::class, 1);
        $this->assertNull(Referral::sole()->inbox_confirmed_at);
    }

    public function test_combined_review_invalidates_an_older_preview_after_reason_or_municipality_changes(): void
    {
        [$report, $admin] = $this->fixture();
        $magdalena = $this->agency('Magdalena');
        $pagsanjan = $this->agency('Pagsanjan');
        $url = route('admin.referrals.review', $report);
        $this->actingAs($admin);
        $first = $this->postJson($url, [...$this->selection($report, $pagsanjan), 'include_photo' => 0])->assertOk();
        $this->postJson($url, [...$this->selection($report, $magdalena, 'Corrected after reviewing the incident point.'), 'include_photo' => 0])->assertOk();
        $this->post(route('admin.referrals.store', $report), ['agency_id' => $pagsanjan->id, 'include_photo' => 0,
            'review_hash' => $first->json('hash'), 'approved' => 1])->assertSessionHasErrors('agency_id');
        $report->forceFill(['latitude' => 14.2])->save();
        $this->postJson($url, [...$this->selection($report, $magdalena), 'expected_status' => 'verified', 'include_photo' => 0])->assertConflict();
        $this->assertDatabaseCount('report_reviews', 2);
        $this->assertDatabaseCount('referrals', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_report_four_requires_explicit_incident_selection_and_routes_to_magdalena_without_changing_the_water_body(): void
    {
        [$report, $admin] = $this->fixture();
        $pagsanjan = $this->agency('Pagsanjan');
        $magdalena = $this->agency('Magdalena');
        $original = $report->fresh()->getAttributes();
        $this->actingAs($admin)->get(route('admin.reports.show', $report))->assertOk()
            ->assertViewHas('suggestedRecipient', null)->assertSee('Water-body recorded municipality:')
            ->assertSee('Pagsanjan')->assertSee('Magdalena')->assertSee('no verified municipality boundary layer');
        $this->post(route('admin.referrals.store', $report), ['agency_id' => $pagsanjan->id, 'include_photo' => 0,
            'review_hash' => ReferralWorkflow::reviewHash($report, $pagsanjan, false), 'approved' => 1])->assertSessionHasErrors('agency_id');
        $this->post(route('admin.reports.municipality', $report), $this->selection($report, $magdalena, '   '))->assertSessionHasErrors('routing_reason');
        $this->post(route('admin.reports.municipality', $report), $this->selection($report, $magdalena))->assertSessionHasNoErrors();
        $this->post(route('admin.reports.municipality', $report), $this->selection($report, $magdalena))->assertSessionHasNoErrors();
        $this->assertSame($original, $report->fresh()->getAttributes());
        $this->assertDatabaseCount('report_reviews', 1);
        $this->assertDatabaseCount('referrals', 0);
        $this->assertSame($admin->id, ReportReview::sole()->administrator_id);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $this->get(route('admin.reports.show', $report))->assertOk()->assertViewHas('suggestedRecipient', $magdalena->id)
            ->assertViewHas('sendOptions', fn ($options) => array_keys($options) === [$magdalena->id])
            ->assertSee('Incident municipality selected: Magdalena');
        $data = ['agency_id' => $magdalena->id, 'include_photo' => 0, 'approved' => 1,
            'review_hash' => ReferralWorkflow::reviewHash($report, $magdalena, false)];
        $this->post(route('admin.referrals.store', $report), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.referrals.store', $report), $data)->assertSessionHasNoErrors();
        $referral = Referral::sole();
        $this->assertSame($magdalena->id, $referral->agency_id);
        $this->assertSame('Magdalena', $referral->payload['incident_municipality']);
        $this->assertSame('Pagsanjan', $referral->payload['municipality']);
        $this->assertSame('Magdalena', $referral->recipient_snapshot['municipality']);
        Queue::assertNothingPushed();
        (new SendReferral($referral->id))->handle();
        (new SendReferral($referral->id))->handle();
        Mail::assertSent(EnvironmentalReferral::class, 1);
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertSame('Pagsanjan', $report->waterBody->fresh()->municipality);
        $this->post(route('admin.reports.municipality', $report), [...$this->selection($report->fresh(), $pagsanjan), 'expected_status' => 'verified'])->assertConflict();
    }

    public function test_selection_changes_need_a_reason_are_audited_and_old_points_and_previews_cannot_be_reused(): void
    {
        [$report, $admin] = $this->fixture();
        $first = $this->agency('Pagsanjan');
        $second = $this->agency('Magdalena');
        $url = route('admin.reports.municipality', $report);
        $data = $this->selection($report, $first);
        $this->post($url, $data)->assertRedirectToRoute('admin.login');
        $this->actingAs(User::factory()->create())->post($url, $data)->assertForbidden();
        $this->actingAs($admin)->post($url, $data)->assertSessionHasNoErrors();
        $oldHash = ReferralWorkflow::reviewHash($report, $first, false);
        $this->post($url, $this->selection($report, $second, ''))->assertSessionHasErrors('routing_reason');
        $this->post($url, $this->selection($report, $second, '<script>unsafeRouting()</script> Reviewed incident placement.'))->assertSessionHasNoErrors();
        $this->assertSame(['Pagsanjan', 'Magdalena'], $report->reviews()->get()->map(fn ($review) => $review->incidentRouting()['municipality'])->all());
        $this->post(route('admin.referrals.store', $report), ['agency_id' => $first->id, 'include_photo' => 0,
            'review_hash' => $oldHash, 'approved' => 1])->assertSessionHasErrors('agency_id');
        $this->get(route('admin.reports.show', $report))->assertSee('&lt;script&gt;unsafeRouting()', false)->assertDontSee('<script>unsafeRouting()', false);
        $report->forceFill(['latitude' => 14.20])->save();
        $this->assertNull(MunicipalityRouting::selection($report->fresh()));
        $this->post($url, $data)->assertConflict();
        $second->forceFill(['active' => false])->save();
        $this->post($url, $this->selection($report->fresh(), $second))->assertUnprocessable();
        $this->assertDatabaseCount('referrals', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_missing_or_uncertain_points_never_guess_from_the_water_body_or_nearest_town(): void
    {
        [$report] = $this->fixture();
        foreach ([[14.19, 121.43], [14.20, 121.44], [14.21, 121.45], [null, null]] as [$latitude, $longitude]) {
            $report->forceFill(['latitude' => $latitude, 'longitude' => $longitude, 'location_accuracy_m' => 1000])->save();
            $result = MunicipalityRouting::incident($report);
            $this->assertNull($result['municipality']);
            $this->assertSame($latitude === null ? 'missing_incident_point' : 'boundaries_unavailable', $result['status']);
        }
        $this->assertDatabaseCount('report_reviews', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_magdalena_addition_is_idempotent_and_preserves_existing_admin_edits(): void
    {
        $this->fixture();
        $existing = $this->agency('Pagsanjan');
        $before = $existing->fresh()->getAttributes();
        $this->artisan('agencies:ensure-magdalena')->assertExitCode(0);
        $magdalena = Agency::where('municipality', 'Magdalena')->sole();
        $this->assertSame(Referral::TEST_RECIPIENT, $magdalena->email);
        $this->assertSame('provisional', $magdalena->verification_status);
        $magdalena->forceFill(['email' => 'edited@example.test', 'active' => false, 'notes' => 'Preserve administrator edits.'])->save();
        $edited = $magdalena->fresh()->getAttributes();
        $this->artisan('agencies:ensure-magdalena')->assertExitCode(0);
        $this->assertSame($edited, $magdalena->fresh()->getAttributes());
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('agencies', 2);
        $this->assertDatabaseCount('report_reviews', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
