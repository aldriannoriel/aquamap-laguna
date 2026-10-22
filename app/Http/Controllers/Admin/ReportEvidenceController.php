<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PollutionReport;
use App\Models\ReportEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportEvidenceController extends Controller
{
    public function show(Request $request, PollutionReport $pollutionReport, ReportEvidence $evidence): StreamedResponse
    {
        abort_unless($evidence->review()->where('pollution_report_id', $pollutionReport->id)->exists(), 404);
        abort_unless(preg_match('~\A'.preg_quote((string) $pollutionReport->id, '~').'/'.preg_quote((string) $evidence->report_review_id, '~').'/[a-f0-9-]{36}\.(jpg|jpeg|png|webp|pdf|doc|docx)\z~D', $evidence->path), 404);
        $disk = Storage::disk('report-evidence');
        abort_unless($disk->exists($evidence->path) && $disk->size($evidence->path) === $evidence->byte_size
            && hash_equals($evidence->sha256, hash('sha256', $disk->get($evidence->path))), 404);

        return $disk->response($evidence->path, $evidence->original_name, ['Content-Type' => $evidence->mime_type,
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'"], $evidence->isImage() && ! $request->boolean('download') ? 'inline' : 'attachment');
    }
}
