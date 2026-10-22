<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\Referral;
use App\Models\ReportReview;
use App\Models\User;
use App\Models\WaterBody;
use App\Support\ReferralWorkflow;
use Database\Seeders\ExplorerSeeder;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('report-evidence');
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function report(array $attributes = []): PollutionReport
    {
        if (! WaterBody::exists()) {
            $this->seed(WaterBodySeeder::class);
        }

        return PollutionReport::forceCreate(array_replace([
            'water_body_id' => WaterBody::where('name', 'Pandin Lake')->sole()->id,
            'pollution_type' => 'solid_waste', 'description' => 'Private synthetic observation for isolated tests only.',
            'status' => 'pending',
        ], $attributes));
    }

    private function forwarded(PollutionReport $report, User $admin): Referral
    {
        return Referral::forceCreate(['reference' => fake()->uuid(), 'dedupe_key' => 'report:'.$report->id,
            'pollution_report_id' => $report->id, 'approved_by' => $admin->id, 'approved_at' => now(),
            'recipient_snapshot' => ['name' => 'Isolated municipality', 'email' => 'municipality@example.test'],
            'payload' => ReferralWorkflow::payload($report, false), 'status' => 'accepted', 'queued_at' => now(), 'sent_at' => now()]);
    }

    public function test_follow_up_and_resolution_never_publish_report_details_or_categories(): void
    {
        $report = $this->report(['pollution_type' => 'plastic_waste']);
        $admin = $this->admin();
        $pending = $this->get(route('map.index'))->assertOk();
        $this->assertArrayNotHasKey('report_categories', $pending->viewData('profiles')[$report->water_body_id]);

        $this->actingAs($admin)->patch(route('admin.reports.status', $report), ['status' => 'verified', 'expected_status' => 'pending'])
            ->assertRedirectToRoute('admin.reports.show', $report)->assertSessionHasNoErrors();
        $verified = $this->get(route('map.index'))->assertOk()->assertDontSee($report->description);
        $this->assertArrayNotHasKey('report_categories', $verified->viewData('profiles')[$report->water_body_id]);

        $this->forwarded($report->fresh(), $admin);
        $this->patch(route('admin.reports.status', $report), ['status' => 'resolved', 'expected_status' => 'verified',
            'review_note' => 'Follow-up inspection completed.', 'resolution_confirmed' => 1, 'supporting_evidence' => [UploadedFile::fake()->image('cleanup.jpg')], 'resolution_basis' => 'Municipality inspection record FIXTURE-01 confirms cleanup.'])
            ->assertRedirectToRoute('admin.reports.show', $report)->assertSessionHasNoErrors();
        $resolved = $this->get(route('map.index'))->assertOk()->assertDontSee($report->description);
        $this->assertArrayNotHasKey('report_categories', $resolved->viewData('profiles')[$report->water_body_id]);
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame(['verified', 'resolved'], $report->reviews()->pluck('new_status')->all());
        $this->assertDatabaseCount('pollution_reports', 1);
    }

    #[TestWith(['Pagsanjan Falls'])]
    #[TestWith(['Taytay Falls'])]
    public function test_waterfall_report_search_filters_and_details_use_the_preserved_primary_name(string $name): void
    {
        $this->seed(WaterBodySeeder::class);
        $this->seed(ExplorerSeeder::class);
        $lake = WaterBody::where('name', $name)->sole();
        $report = $this->report(['water_body_id' => $lake->id]);
        $before = $lake->toArray();
        $this->actingAs($this->admin());

        $this->get(route('admin.reports.index', ['q' => $name]))->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === [$report->id])
            ->assertSee('Report #'.$report->id.' &middot; '.$name, false)
            ->assertSee('>'.$name.'</option>', false);
        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee('<h2 class="text-xl font-semibold">'.$name.'</h2>', false);
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('counts.total', 1);

        $this->assertSame($before, $lake->fresh()->toArray());
        $this->assertSame($lake->id, $report->fresh()->water_body_id);
        $this->assertDatabaseCount('water_bodies', 28);
    }

    public function test_dashboard_maps_unforwarded_and_unknown_legacy_statuses_to_pending_without_rewriting_them(): void
    {
        foreach (['pending', 'pending', 'verified', 'rejected', 'resolved', 'legacy_unknown'] as $status) {
            $this->report(['status' => $status]);
        }
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('counts', ['total' => 6, 'pending' => 4, 'forwarded' => 0, 'resolved' => 1, 'rejected' => 1, 'closed' => 0])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('pollution_reports', ['status' => 'legacy_unknown']);
    }

    public function test_filters_search_dates_intersections_and_pagination_retrieve_only_matching_records(): void
    {
        $first = $this->report(['description' => 'Isolated shoreline bags fixture.', 'created_at' => '2026-01-02 10:00:00']);
        $second = $this->report(['status' => 'verified', 'pollution_type' => 'other', 'created_at' => '2026-01-03 10:00:00']);
        $third = $this->report(['water_body_id' => WaterBody::where('name', 'Yambo Lake')->sole()->id,
            'status' => 'resolved', 'created_at' => '2026-01-04 10:00:00']);
        $this->actingAs($this->admin());
        foreach ([['q' => 'shoreline'], ['type' => 'solid_waste', 'status' => 'pending'], ['from' => '2026-01-02', 'to' => '2026-01-02']] as $filter) {
            $this->get(route('admin.reports.index', $filter))->assertOk()
                ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === [$first->id]);
        }
        $this->get(route('admin.reports.index', ['status' => 'verified']))->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === [$second->id]);
        $this->get(route('admin.reports.index', ['lake' => $third->water_body_id]))->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === [$third->id]);
        $this->get(route('admin.reports.index', ['q' => "' OR 1=1 --"]))->assertViewHas('reports', fn ($reports) => $reports->total() === 0);
        for ($i = 0; $i < 14; $i++) {
            $this->report();
        }
        $this->get(route('admin.reports.index'))->assertViewHas('reports', fn ($reports) => $reports->count() === 15 && $reports->total() === 17)->assertSee('page=2');
        $this->get(route('admin.reports.index', ['page' => 2]))->assertViewHas('reports', fn ($reports) => $reports->count() === 2);
    }

    #[TestWith(['lake', 9999])]
    #[TestWith(['status', 'approved'])]
    #[TestWith(['type', 'invented'])]
    #[TestWith(['q', ['invalid-shape']])]
    #[TestWith(['from', '2026-02-30'])]
    #[TestWith(['page', -1])]
    public function test_invalid_filter_input_is_rejected_and_does_not_loop(string $field, mixed $value): void
    {
        $this->actingAs($this->admin())->get(route('admin.reports.index', [$field => $value]))
            ->assertRedirectToRoute('admin.reports.index')->assertSessionHasErrors($field);
        $this->get(route('admin.reports.index'))->assertOk();
    }

    public function test_date_range_cannot_end_before_starting_date(): void
    {
        $this->actingAs($this->admin())->get(route('admin.reports.index', ['from' => '2026-01-03', 'to' => '2026-01-02']))->assertSessionHasErrors('to');
    }

    public function test_private_details_are_escaped_and_public_routes_do_not_expose_reports_or_audits(): void
    {
        $report = $this->report(['reporter_email' => 'private-person@example.test', 'reporter_name' => '<script>privateName()</script>',
            'description' => '<script>privateObservation()</script>', 'latitude' => 14, 'longitude' => 121, 'photo_path' => 'private-fixture.png']);
        $this->actingAs($this->admin())->get(route('admin.reports.show', $report))->assertOk()
            ->assertSee('private-person@example.test')->assertSee('&lt;script&gt;privateObservation()', false)
            ->assertDontSee('<script>privateName()', false)->assertDontSee('private-fixture.png')
            ->assertSee('Latitude 14.0000000')->assertSee('unverified');
        foreach (['map.index', 'reports.create', 'admin.login'] as $route) {
            $response = $this->get(route($route));
            $response->assertDontSee('private-person@example.test')->assertDontSee('privateObservation()')->assertDontSee('private-fixture.png');
        }
        $this->get('/reports/'.$report->id)->assertNotFound();
    }

    public function test_photo_access_requires_admin_and_streams_safe_image_without_exposing_its_path(): void
    {
        Storage::fake('pollution-reports');
        $path = UploadedFile::fake()->image('fixture.png')->store('', 'pollution-reports');
        $report = $this->report(['photo_path' => $path]);
        $this->get(route('admin.reports.photo', $report))->assertRedirectToRoute('admin.login');
        $this->actingAs(User::factory()->create())->get(route('admin.reports.photo', $report))->assertForbidden();
        $this->patch(route('admin.reports.status', $report), ['status' => 'verified', 'expected_status' => 'pending'])->assertForbidden();
        $this->assertDatabaseCount('report_reviews', 0);
        $this->actingAs($this->admin())->get(route('admin.reports.photo', $report))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename=private-evidence.png');
        Storage::disk('pollution-reports')->assertExists($path);
        $this->get('/storage/pollution-reports/'.$path)->assertForbidden();
    }

    #[TestWith([null])]
    #[TestWith(['../unsafe.png'])]
    #[TestWith(['C:\\private\\unsafe.png'])]
    #[TestWith(['missing.png'])]
    public function test_missing_or_unsafe_photo_paths_are_not_served(?string $path): void
    {
        Storage::fake('pollution-reports');
        $report = $this->report(['photo_path' => $path]);
        $this->actingAs($this->admin())->get(route('admin.reports.photo', $report))->assertNotFound();
        $this->assertSame([], Storage::disk('pollution-reports')->allFiles());
    }

    public function test_private_file_with_unexpected_content_is_not_served(): void
    {
        Storage::fake('pollution-reports');
        Storage::disk('pollution-reports')->put('unsafe.png', '<?php unsafe();');
        $report = $this->report(['photo_path' => 'unsafe.png']);
        $this->actingAs($this->admin())->get(route('admin.reports.photo', $report))->assertNotFound();
        Storage::disk('pollution-reports')->assertExists('unsafe.png');
    }

    #[TestWith(['pending', 'verified', true])]
    #[TestWith(['pending', 'rejected', true])]
    #[TestWith(['verified', 'resolved', false])]
    #[TestWith(['pending', 'resolved', false])]
    #[TestWith(['pending', 'pending', false])]
    #[TestWith(['verified', 'pending', false])]
    #[TestWith(['verified', 'verified', false])]
    #[TestWith(['verified', 'rejected', true])]
    #[TestWith(['rejected', 'pending', false])]
    #[TestWith(['rejected', 'verified', false])]
    #[TestWith(['rejected', 'rejected', false])]
    #[TestWith(['rejected', 'resolved', false])]
    #[TestWith(['resolved', 'pending', false])]
    #[TestWith(['resolved', 'verified', false])]
    #[TestWith(['resolved', 'rejected', false])]
    #[TestWith(['resolved', 'resolved', false])]
    public function test_transition_matrix_and_audit_fields_are_server_controlled(string $from, string $to, bool $valid): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = $this->admin();
        $report = $this->report(['status' => $from]);
        $response = $this->actingAs($admin)->from(route('admin.reports.show', $report))->patch(route('admin.reports.status', $report), [
            'expected_status' => $from, 'status' => $to, 'review_note' => 'Isolated decision fixture.',
            'rejection_reason' => 'Isolated decision fixture.', ...($to === 'resolved' ? ['resolution_confirmed' => 1, 'supporting_evidence' => [UploadedFile::fake()->image('cleanup.jpg')]] : []),
            'administrator_id' => 9999, 'previous_status' => 'invented', 'created_at' => '2000-01-01',
        ]);
        if ($valid) {
            $response->assertRedirectToRoute('admin.reports.show', $report)->assertSessionHas('review_saved', true);
            $this->assertSame($to, $report->fresh()->status);
            $review = ReportReview::sole();
            $this->assertSame($admin->id, $review->administrator_id);
            $this->assertSame($from, $review->previous_status);
            $this->assertSame($to, $review->new_status);
            $this->assertSame($to === 'rejected' ? "Rejection reason:\nIsolated decision fixture." : 'Isolated decision fixture.', $review->review_note);
            $this->assertTrue($review->created_at->equalTo(now()));
            $this->assertTrue($review->pollutionReport->is($report));
            $this->assertTrue($review->administrator->is($admin));
        } else {
            $response->assertSessionHasErrors('status');
            $this->assertSame($from, $report->fresh()->status);
            $this->assertDatabaseCount('report_reviews', 0);
        }
    }

    public function test_stale_review_is_refused_and_history_is_escaped_and_read_only(): void
    {
        $report = $this->report();
        $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), ['expected_status' => 'pending', 'status' => 'verified', 'review_note' => '<script>auditFixture()</script>'])->assertSessionHasNoErrors();
        $this->patch(route('admin.reports.status', $report), ['expected_status' => 'pending', 'status' => 'resolved',
            'review_note' => 'Stale review.', 'resolution_confirmed' => 1, 'supporting_evidence' => [UploadedFile::fake()->image('cleanup.jpg')], 'resolution_basis' => 'Stale documented inspection.'])->assertSessionHasErrors('status');
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 1);
        $this->get(route('admin.reports.show', $report))->assertSee('Pending to Verified')->assertSee('&lt;script&gt;auditFixture()', false)->assertDontSee('<script>auditFixture()', false);
    }

    public function test_missing_status_oversized_or_array_notes_and_unknown_status_are_rejected(): void
    {
        $report = $this->report();
        $this->actingAs($this->admin());
        foreach ([['status' => 'invented'], ['review_note' => str_repeat('a', 2001)], ['review_note' => ['unsafe']]] as $change) {
            $this->patch(route('admin.reports.status', $report), array_replace(['status' => 'verified', 'expected_status' => 'pending'], $change))->assertSessionHasErrors();
            $this->get(route('admin.reports.show', $report))->assertOk();
        }
        $this->patch(route('admin.reports.status', $report), [])->assertSessionHasErrors(['status', 'expected_status']);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_sensitive_review_action_is_rate_limited(): void
    {
        $this->freezeTime();
        $report = $this->report();
        $this->actingAs($this->admin());
        for ($i = 0; $i < 30; $i++) {
            $this->patch(route('admin.reports.status', $report), [])->assertSessionHasErrors();
        }
        $this->patch(route('admin.reports.status', $report), [])->assertStatus(429);
        $this->assertSame('pending', $report->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_forwarded_follow_up_preserves_delivery_evidence_and_requires_a_documented_resolution(): void
    {
        Mail::fake();
        Queue::fake();
        Storage::fake('pollution-reports');
        $path = UploadedFile::fake()->image('original.png')->store('', 'pollution-reports');
        $report = $this->report(['status' => 'verified', 'photo_path' => $path, 'latitude' => 14.11, 'longitude' => 121.33]);
        $admin = $this->admin();
        $delivery = $this->forwarded($report, $admin);
        $deliveryBefore = $delivery->fresh()->getAttributes();
        $original = $report->only(['created_at', 'latitude', 'longitude', 'photo_path', 'description']);
        $this->actingAs($admin)->get(route('admin.reports.show', $report))->assertOk()
            ->assertSee('Report status:')->assertSee('Email delivery:')->assertSee('Report follow-up')->assertDontSee('id="reject-report-form"', false);
        $url = route('admin.reports.status', $report);
        $this->patch($url, ['status' => 'rejected', 'expected_status' => 'verified', 'rejection_reason' => 'Must use separate closure.'])->assertSessionHasErrors('status');
        $this->patch($url, ['status' => 'under_review', 'expected_status' => 'verified', 'review_note' => 'Municipality review started.'])->assertSessionHasNoErrors();
        $this->patch($url, ['status' => 'in_progress', 'expected_status' => 'under_review', 'review_note' => 'Cleanup scheduled.'])->assertSessionHasNoErrors();
        $this->patch($url, ['status' => 'resolved', 'expected_status' => 'in_progress'])->assertSessionHasErrors(['supporting_evidence', 'resolution_confirmed']);
        $this->patch($url, ['status' => 'resolved', 'expected_status' => 'in_progress', 'review_note' => 'Completion reported.'])->assertSessionHasErrors('supporting_evidence');
        $this->assertSame('in_progress', $report->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 2);
        $this->patch($url, ['status' => 'resolved', 'expected_status' => 'in_progress', 'review_note' => '<script>unsafeBasis()</script> Inspection completed.',
            'resolution_confirmed' => 1, 'supporting_evidence' => [UploadedFile::fake()->image('cleanup.jpg')], 'resolution_basis' => '<script>unsafeBasis()</script> Inspection reference FIXTURE-02.'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame(['under_review', 'in_progress', 'resolved'], $report->reviews()->pluck('new_status')->all());
        $this->assertSame($deliveryBefore, $delivery->fresh()->getAttributes());
        $this->assertEquals($original, $report->fresh()->only(array_keys($original)));
        Storage::disk('pollution-reports')->assertExists($path);
        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee('&lt;script&gt;unsafeBasis()', false)
            ->assertSee('Resolution Evidence')->assertSee('cleanup.jpg')->assertDontSee('<script>unsafeBasis()', false);
        $this->patch($url, ['status' => 'under_review', 'expected_status' => 'resolved'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('report_reviews', 3);
        $this->get(route('admin.reports.index', ['queue' => 'processed', 'status' => 'resolved']))
            ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === [$report->id]);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_unforwarded_reports_cannot_enter_follow_up_and_forwarded_closure_requires_its_own_reason(): void
    {
        $report = $this->report(['status' => 'verified']);
        $admin = $this->admin();
        $url = route('admin.reports.status', $report);
        $this->actingAs($admin);
        foreach (['under_review', 'in_progress', 'resolved', 'closed'] as $status) {
            $this->patch($url, ['status' => $status, 'expected_status' => 'verified', 'review_note' => 'No referral yet.',
                ...($status === 'resolved' ? ['resolution_confirmed' => 1, 'supporting_evidence' => [UploadedFile::fake()->image('cleanup.jpg')]] : []), 'closure_reason' => 'Cannot close as forwarded.'])->assertSessionHasErrors('status');
        }
        $this->assertDatabaseCount('report_reviews', 0);
        $this->forwarded($report, $admin);
        $this->patch($url, ['status' => 'closed', 'expected_status' => 'verified', 'review_note' => 'Administrative follow-up.'])->assertSessionHasErrors('closure_reason');
        $this->patch($url, ['status' => 'closed', 'expected_status' => 'verified', 'closure_reason' => 'Merged into municipality case FIXTURE-03.'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $report->fresh()->status);
        $this->assertStringContainsString("Closure reason (not resolved):\nMerged into municipality case FIXTURE-03.", ReportReview::sole()->review_note);
        $this->patch($url, ['status' => 'under_review', 'expected_status' => 'closed'])->assertSessionHasErrors('reopen_reason');
    }

    public function test_five_categories_partition_reports_and_filter_on_smtp_acceptance_instead_of_approval(): void
    {
        $admin = $this->admin();
        $pending = $this->report(['status' => 'verified']);
        $queued = $this->forwarded($pending, $admin);
        $queued->forceFill(['status' => 'queued', 'sent_at' => null])->save();
        $expected = ['pending' => [$pending->id]];
        foreach (['verified', 'under_review', 'in_progress', 'resolved', 'rejected', 'closed'] as $status) {
            $report = $this->report(['status' => $status]);
            $this->forwarded($report, $admin);
            $expected[in_array($status, ['resolved', 'rejected', 'closed'], true) ? $status : 'forwarded'][] = $report->id;
        }
        $attributes = PollutionReport::all()->map->getAttributes()->all();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('counts', ['total' => 7, 'pending' => 1, 'forwarded' => 3, 'resolved' => 1, 'rejected' => 1, 'closed' => 1]);
        foreach ($expected as $category => $ids) {
            rsort($ids);
            $this->get(route('admin.reports.index', ['category' => $category]))->assertOk()
                ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === $ids);
        }
        $this->assertSame($attributes, PollutionReport::all()->map->getAttributes()->all());
        $this->get(route('admin.reports.index', ['category' => 'invented']))->assertSessionHasErrors('category');
    }

    public function test_closed_cases_reopen_only_with_admin_reason_and_saved_forwarding_and_preserve_closure_and_email(): void
    {
        Mail::fake();
        Queue::fake();
        $admin = $this->admin();
        $report = $this->report(['status' => 'verified']);
        $delivery = $this->forwarded($report, $admin);
        $deliveryBefore = $delivery->fresh()->getAttributes();
        $url = route('admin.reports.status', $report);
        $this->actingAs($admin)->patch($url, ['status' => 'closed', 'expected_status' => 'verified',
            'closure_reason' => 'Administrative transfer without confirmed cleanup.', 'follow_up_attempts' => 'Contacted municipality twice.'])->assertSessionHasNoErrors();
        $closure = ReportReview::sole()->getAttributes();
        $this->assertStringContainsString('Contacted municipality twice.', $closure['review_note']);
        $data = ['status' => 'under_review', 'expected_status' => 'closed', 'reopen_reason' => 'New inspection evidence received.'];
        $this->actingAs(User::factory()->create())->patch($url, $data)->assertForbidden();
        $this->actingAs($admin)->patch($url, [...$data, 'status' => 'resolved', 'review_note' => 'Invalid shortcut.', 'resolution_confirmed' => 1, 'supporting_evidence' => [UploadedFile::fake()->image('cleanup.jpg')], 'resolution_basis' => 'Invalid.'])->assertSessionHasErrors('status');
        $this->patch($url, [...$data, 'reopen_reason' => '   '])->assertSessionHasErrors('reopen_reason');
        $this->patch($url, $data)->assertSessionHasNoErrors();
        $this->assertSame('under_review', $report->fresh()->status);
        $this->assertSame('forwarded', $report->fresh()->category);
        $this->assertSame($closure, ReportReview::first()->getAttributes());
        $this->assertSame($admin->id, ReportReview::latest('id')->first()->administrator_id);
        $this->assertStringContainsString('New inspection evidence received.', ReportReview::latest('id')->first()->review_note);
        $this->patch($url, $data)->assertSessionHasErrors('status');
        $this->assertDatabaseCount('report_reviews', 2);
        $this->assertSame($deliveryBefore, $delivery->fresh()->getAttributes());
        $unforwarded = $this->report(['status' => 'closed']);
        $this->patch(route('admin.reports.status', $unforwarded), $data)->assertSessionHasErrors('status');
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_audit_failure_rolls_back_the_status_change(): void
    {
        $report = $this->report();
        ReportReview::creating(function (): void {
            throw new \RuntimeException('Isolated audit failure fixture.');
        });
        try {
            $this->actingAs($this->admin())->patch(route('admin.reports.status', $report), ['expected_status' => 'pending', 'status' => 'verified'])->assertRedirectToRoute('admin.reports.show', $report)->assertSessionHasErrors('status');
            $this->assertSame('pending', $report->fresh()->status);
            $this->assertDatabaseCount('report_reviews', 0);
        } finally {
            ReportReview::flushEventListeners();
        }
    }

    #[TestWith(['pending', 'sending', false])]
    #[TestWith(['verified', 'sending', false])]
    #[TestWith(['verified', 'sending', true])]
    #[TestWith(['verified', 'uncertain', true])]
    public function test_unacknowledged_submission_shows_controls_with_the_documented_prerequisite(string $status, string $deliveryStatus, bool $stale): void
    {
        $this->freezeTime();
        Mail::fake();
        Queue::fake();
        $report = $this->report(['status' => $status]);
        $admin = $this->admin();
        $delivery = $this->forwarded($report, $admin);
        $delivery->forceFill(['status' => $deliveryStatus, 'sent_at' => null,
            'sending_at' => $deliveryStatus === 'preparing' ? null : now(),
            'updated_at' => $stale ? now()->subMinutes(6) : now()])->save();
        $before = $delivery->fresh()->getAttributes();
        $response = $this->actingAs($admin)->get(route('admin.reports.show', $report))->assertOk()
            ->assertSee('Follow-up controls require recorded SMTP acceptance')->assertSee('Mark as Resolved')->assertSee('Close Case')
            ->assertSee('Supporting Evidence')->assertDontSee('id="reject-report-form"', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@data-report-follow-up]/fieldset[@disabled]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-report-follow-up]//select[@name="status"]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-report-follow-up]//input[@type="file"]')->length);
        if ($stale && $deliveryStatus !== 'uncertain') {
            $response->assertSee('Reconcile interrupted delivery')->assertSee($deliveryStatus === 'sending' ? 'Delivery outcome unknown' : 'Preparation interrupted');
        } else {
            $response->assertDontSee('Reconcile interrupted delivery');
        }
        foreach (['under_review', 'in_progress', 'resolved', 'closed', 'rejected'] as $target) {
            $this->patch(route('admin.reports.status', $report), ['status' => $target, 'expected_status' => $status,
                'closure_reason' => 'Administrative closure.', 'rejection_reason' => 'Unsafe cancellation.',
                ...($target === 'resolved' ? ['supporting_evidence' => [UploadedFile::fake()->image('cleanup.png')], 'resolution_confirmed' => 1] : [])])
                ->assertSessionHasErrors('status');
        }
        $this->assertSame($status, $report->fresh()->status);
        $this->assertSame($before, $delivery->fresh()->getAttributes());
        $this->assertDatabaseCount('report_reviews', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    #[TestWith(['pending'])]
    #[TestWith(['verified'])]
    public function test_saved_acceptance_unlocks_controls_without_resolving_the_report(string $status): void
    {
        $report = $this->report(['status' => $status]);
        $admin = $this->admin();
        $this->forwarded($report, $admin);
        $response = $this->actingAs($admin)->get(route('admin.reports.show', $report))->assertOk()
            ->assertSee('Mark as Resolved')->assertSee('Close Case')->assertSee('Supporting Evidence');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@data-report-follow-up]/fieldset[not(@disabled)]')->length);
        $this->assertSame($status, $report->fresh()->status);
        $url = route('admin.reports.status', $report);
        $this->patch($url, ['status' => 'resolved', 'expected_status' => $status])->assertSessionHasErrors(['supporting_evidence', 'resolution_confirmed']);
        $this->patch($url, ['status' => 'closed', 'expected_status' => $status])->assertSessionHasErrors('closure_reason');
        $this->patch($url, ['status' => 'resolved', 'expected_status' => $status, 'review_note' => '',
            'supporting_evidence' => [UploadedFile::fake()->image('cleanup.png')], 'resolution_confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $report->fresh()->status);
    }
}
