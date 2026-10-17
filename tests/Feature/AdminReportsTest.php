<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\ReportReview;
use App\Models\User;
use App\Models\WaterBody;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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

    public function test_dashboard_counts_each_status_and_includes_unknown_legacy_rows_only_in_total(): void
    {
        foreach (['pending', 'pending', 'verified', 'rejected', 'resolved', 'legacy_unknown'] as $status) {
            $this->report(['status' => $status]);
        }
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('counts', ['total' => 6, 'pending' => 2, 'verified' => 1, 'rejected' => 1, 'resolved' => 1])
            ->assertHeader('Cache-Control', 'no-store, private');
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
    #[TestWith(['verified', 'resolved', true])]
    #[TestWith(['pending', 'resolved', false])]
    #[TestWith(['pending', 'pending', false])]
    #[TestWith(['verified', 'pending', false])]
    #[TestWith(['verified', 'verified', false])]
    #[TestWith(['verified', 'rejected', false])]
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
            'administrator_id' => 9999, 'previous_status' => 'invented', 'created_at' => '2000-01-01',
        ]);
        if ($valid) {
            $response->assertRedirectToRoute('admin.reports.show', $report)->assertSessionHas('review_saved', true);
            $this->assertSame($to, $report->fresh()->status);
            $review = ReportReview::sole();
            $this->assertSame($admin->id, $review->administrator_id);
            $this->assertSame($from, $review->previous_status);
            $this->assertSame($to, $review->new_status);
            $this->assertSame('Isolated decision fixture.', $review->review_note);
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
        $this->patch(route('admin.reports.status', $report), ['expected_status' => 'pending', 'status' => 'resolved'])->assertSessionHasErrors('status');
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
}
