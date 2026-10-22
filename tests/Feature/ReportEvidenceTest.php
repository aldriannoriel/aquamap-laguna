<?php

namespace Tests\Feature;

use App\Models\PollutionReport;
use App\Models\Referral;
use App\Models\ReportEvidence;
use App\Models\ReportReview;
use App\Models\User;
use App\Models\WaterBody;
use App\Support\ReferralWorkflow;
use Database\Seeders\WaterBodySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class ReportEvidenceTest extends TestCase
{
    private function fixture(): array
    {
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->seed(WaterBodySeeder::class);
        Storage::fake('report-evidence');
        Storage::fake('pollution-reports');
        Mail::fake();
        Queue::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $original = UploadedFile::fake()->image('incident.jpg')->store('', 'pollution-reports');
        $report = PollutionReport::forceCreate(['water_body_id' => WaterBody::firstOrFail()->id, 'status' => 'verified',
            'pollution_type' => 'plastic_waste', 'description' => 'Private synthetic concern', 'photo_path' => $original]);
        Referral::forceCreate(['reference' => fake()->uuid(), 'dedupe_key' => 'report:'.$report->id, 'pollution_report_id' => $report->id,
            'approved_by' => $admin->id, 'approved_at' => now(), 'recipient_snapshot' => ['name' => 'Fixture municipality', 'email' => 'fixture@example.test'],
            'payload' => ReferralWorkflow::payload($report, false), 'status' => 'accepted', 'sent_at' => now(), 'queued_at' => now()]);
        $this->actingAs($admin);

        return [$report, $admin];
    }

    private function resolve(PollutionReport $report, array $files, array $extra = []): TestResponse
    {
        return $this->patch(route('admin.reports.status', $report), [...['status' => 'resolved', 'expected_status' => 'verified',
            'resolution_confirmed' => 1, 'supporting_evidence' => $files], ...$extra]);
    }

    private function pdf(string $name = 'confirmation.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    private function docx(bool $unsafe = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'aquamap-docx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Fixture confirmation</w:t></w:r></w:p></w:body></w:document>');
        if ($unsafe) {
            $zip->addFromString('word/vbaProject.bin', 'Unsafe fixture');
        }
        $zip->close();
        $file = UploadedFile::fake()->createWithContent('confirmation.docx', file_get_contents($path));
        unlink($path);

        return $file;
    }

    public function test_resolution_with_evidence_and_empty_remarks_preserves_original_photo_and_history(): void
    {
        [$report, $admin] = $this->fixture();
        $this->freezeTime();
        $originalBytes = Storage::disk('pollution-reports')->get($report->photo_path);
        $delivery = $report->referral->getAttributes();
        $historical = $report->reviews()->forceCreate(['administrator_id' => $admin->id, 'previous_status' => 'verified', 'new_status' => 'verified',
            'review_note' => 'Historical documented reference CASE-123.']);
        $before = $historical->fresh()->getAttributes();
        $this->resolve($report, [UploadedFile::fake()->image('cleanup.jpg')], ['review_note' => ''])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $report->fresh()->status);
        $evidence = ReportEvidence::sole();
        $this->assertSame($report->id, $evidence->review->pollution_report_id);
        $this->assertSame($admin->id, $evidence->review->administrator_id);
        $this->assertSame(now()->toDateTimeString(), $evidence->created_at->toDateTimeString());
        $this->assertSame('resolved', $evidence->review->new_status);
        Storage::disk('report-evidence')->assertExists($evidence->path);
        $this->assertSame($originalBytes, Storage::disk('pollution-reports')->get($report->photo_path));
        $this->assertSame($delivery, $report->referral->fresh()->getAttributes());
        $this->assertSame($before, $historical->fresh()->getAttributes());
        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee('Resolution Evidence')->assertSee('cleanup.jpg')->assertSee('Historical documented reference CASE-123.');
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_resolution_requires_a_file_and_explicit_confirmation_but_not_text_reference(): void
    {
        [$report] = $this->fixture();
        $this->resolve($report, [], ['resolution_basis' => 'Text alone is insufficient.'])->assertSessionHasErrors('supporting_evidence');
        $this->resolve($report, [$this->pdf()], ['resolution_confirmed' => 0])->assertSessionHasErrors('resolution_confirmed');
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertDatabaseCount('report_evidence', 0);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_multiple_photos_pdf_and_docx_are_saved_with_safe_metadata(): void
    {
        [$report] = $this->fixture();
        $this->resolve($report, [UploadedFile::fake()->image('cleanup.jpeg'), UploadedFile::fake()->image('cleanup.png'),
            $this->pdf(), $this->docx()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('report_evidence', 4);
        foreach (ReportEvidence::all() as $evidence) {
            $this->assertStringStartsWith($report->id.'/', $evidence->path);
            $this->assertStringNotContainsString($evidence->original_name, $evidence->path);
            $this->assertSame($evidence->sha256, hash('sha256', Storage::disk('report-evidence')->get($evidence->path)));
            $this->assertGreaterThan(0, $evidence->byte_size);
        }
    }

    public function test_municipality_update_can_use_files_without_remarks_and_does_not_resolve(): void
    {
        [$report] = $this->fixture();
        $this->patch(route('admin.reports.status', $report), ['status' => 'municipality_update', 'expected_status' => 'verified',
            'supporting_evidence' => [$this->pdf()], 'review_note' => ''])->assertSessionHasNoErrors();
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertSame('forwarded', $report->fresh()->category);
        $this->assertSame(ReportReview::sole()->id, ReportEvidence::sole()->report_review_id);
        Mail::assertNothingSent();
    }

    public function test_legacy_doc_and_webp_uploads_are_supported(): void
    {
        [$report] = $this->fixture();
        $header = hex2bin('d0cf11e0a1b11ae1').str_repeat("\0", 16).pack('vvvv', 0x3E, 3, 0xFFFE, 9).pack('v', 6).str_repeat("\0", 6)
            .pack('VVVVVVVVV', 0, 1, 0, 0, 4096, 0xFFFFFFFE, 0, 0xFFFFFFFE, 0).pack('V', 1).str_repeat("\xff", 108 * 4);
        $entry = function (string $name, int $type, int $child, int $start, int $size): string {
            $name = mb_convert_encoding($name."\0", 'UTF-16LE');

            return str_pad($name, 64, "\0").pack('vCCVVV', strlen($name), $type, 1, 0xFFFFFFFF, 0xFFFFFFFF, $child)
                .str_repeat("\0", 36).pack('VVV', $start, $size, 0);
        };
        $directory = $entry('Root Entry', 5, 1, 0xFFFFFFFE, 0).$entry('WordDocument', 2, 0xFFFFFFFF, 2, 4096).str_repeat("\0", 256);
        $fat = array_pad([0xFFFFFFFE, 0xFFFFFFFD, 3, 4, 5, 6, 7, 8, 9, 0xFFFFFFFE], 128, 0xFFFFFFFF);
        $doc = $header.$directory.pack('V*', ...$fat).hex2bin('eca5c100').str_repeat("\0", 4092);
        $this->resolve($report, [UploadedFile::fake()->createWithContent('confirmation.doc', $doc), UploadedFile::fake()->image('cleanup.webp')])->assertSessionHasNoErrors();
        $this->assertSame(['application/msword', 'image/webp'], ReportEvidence::orderBy('id')->pluck('mime_type')->all());
    }

    public function test_closed_requires_a_reason_but_no_evidence_or_remarks(): void
    {
        [$report] = $this->fixture();
        $this->patch(route('admin.reports.status', $report), ['status' => 'closed', 'expected_status' => 'verified'])->assertSessionHasErrors('closure_reason');
        $this->patch(route('admin.reports.status', $report), ['status' => 'closed', 'expected_status' => 'verified', 'closure_reason' => 'Administrative transfer.'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $report->fresh()->status);
        $this->assertDatabaseCount('report_evidence', 0);
    }

    public function test_invalid_oversized_mismatched_active_and_excess_files_are_rejected(): void
    {
        [$report] = $this->fixture();
        foreach ([UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('payload.jpg', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            UploadedFile::fake()->create('huge.pdf', 5121, 'application/pdf'), $this->docx(true),
            UploadedFile::fake()->createWithContent('active.pdf', "%PDF-1.4\n/JavaScript (alert(1))\n%%EOF")] as $file) {
            $this->resolve($report, [$file])->assertSessionHasErrors('supporting_evidence.0');
        }
        $this->resolve($report, array_map(fn () => $this->pdf(), range(1, 6)))->assertSessionHasErrors('supporting_evidence');
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertDatabaseCount('report_evidence', 0);
        $this->assertSame([], Storage::disk('report-evidence')->allFiles());
    }

    public function test_evidence_routes_require_admin_and_the_correct_report_and_never_public_urls(): void
    {
        [$report, $admin] = $this->fixture();
        $this->resolve($report, [$this->pdf(), UploadedFile::fake()->image('proof.png')])->assertSessionHasNoErrors();
        $evidence = ReportEvidence::firstOrFail();
        $url = route('admin.reports.evidence', [$report, $evidence]);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('attachment;', $this->get($url)->headers->get('Content-Disposition'));
        $this->get('/storage/'.$evidence->path)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirectToRoute('admin.login');
        $other = PollutionReport::forceCreate(['water_body_id' => $report->water_body_id, 'status' => 'pending', 'pollution_type' => 'other', 'description' => 'Another private case']);
        $this->actingAs($admin)->get(route('admin.reports.evidence', [$other, $evidence]))->assertNotFound();
        Storage::disk('report-evidence')->put($evidence->path, 'Changed evidence');
        $this->get($url)->assertNotFound();
    }

    public function test_partial_file_storage_failure_rolls_back_status_audit_and_all_new_files(): void
    {
        [$report] = $this->fixture();
        $disk = Storage::disk('report-evidence');
        $proxy = \Mockery::mock($disk)->makePartial();
        $proxy->shouldReceive('put')->once()->ordered()->andReturnUsing(fn (...$args) => $disk->put(...$args));
        $proxy->shouldReceive('put')->once()->ordered()->andReturn(false);
        Storage::shouldReceive('disk')->with('report-evidence')->andReturn($proxy);
        $this->resolve($report, [$this->pdf('first.pdf'), $this->pdf('second.pdf')])->assertSessionHasErrors('status');
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 0);
        $this->assertDatabaseCount('report_evidence', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_database_failure_preserves_existing_evidence_and_removes_only_new_uploads(): void
    {
        [$report] = $this->fixture();
        $this->patch(route('admin.reports.status', $report), ['status' => 'municipality_update', 'expected_status' => 'verified',
            'supporting_evidence' => [$this->pdf('previous.pdf')]])->assertSessionHasNoErrors();
        $previous = ReportEvidence::sole()->getAttributes();
        $paths = Storage::disk('report-evidence')->allFiles();
        ReportEvidence::creating(function (): void {
            throw new \RuntimeException('Synthetic metadata storage failure.');
        });
        try {
            $this->resolve($report, [UploadedFile::fake()->image('new.png')])->assertSessionHasErrors('status');
        } finally {
            ReportEvidence::flushEventListeners();
        }
        $this->assertSame('verified', $report->fresh()->status);
        $this->assertSame($previous, ReportEvidence::sole()->getAttributes());
        $this->assertSame($paths, Storage::disk('report-evidence')->allFiles());
        $this->assertDatabaseCount('report_reviews', 1);
    }

    public function test_untrusted_filenames_are_escaped_and_not_used_as_storage_paths(): void
    {
        [$report] = $this->fixture();
        $name = '<img src=x onerror=alert(1)>.pdf';
        $this->resolve($report, [$this->pdf($name)])->assertSessionHasNoErrors();
        $this->get(route('admin.reports.show', $report))->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;.pdf', false)->assertDontSee($name, false);
        $this->assertStringNotContainsString($name, ReportEvidence::sole()->path);
        $this->assertSame(1, $report->evidence()->count());
    }
}
