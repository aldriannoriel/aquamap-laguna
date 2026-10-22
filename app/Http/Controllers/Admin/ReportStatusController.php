<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewReportRequest;
use App\Models\PollutionReport;
use App\Models\ReportReview;
use App\Support\ReferralWorkflow;
use App\Support\ReportEvidenceStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReportStatusController extends Controller
{
    public function __invoke(ReviewReportRequest $request, PollutionReport $pollutionReport): RedirectResponse
    {
        $data = $request->validated();
        $createdPaths = [];
        try {
            DB::transaction(function () use ($request, $pollutionReport, $data, &$createdPaths): void {
                $report = PollutionReport::query()->lockForUpdate()->findOrFail($pollutionReport->id);
                $report->setRelation('referral', $report->referral()->lockForUpdate()->first());
                $update = $data['status'] === 'municipality_update';
                if ($report->status !== $data['expected_status']
                    || ($update ? (! $report->forwarded() || ! in_array($report->status, ['verified', 'under_review', 'in_progress'], true))
                        : ! in_array($data['status'], $report->availableTransitions(), true))) {
                    throw ValidationException::withMessages(['status' => 'This status change is no longer available. Reload the report and review its current status.'])
                        ->redirectTo(route('admin.reports.show', $pollutionReport));
                }
                if ($data['status'] === 'rejected' && $report->referral && $report->referral->status !== 'cancelled') {
                    $report->referral->forceFill(['status' => 'cancelled', 'claim_token' => null])->save();
                    ReferralWorkflow::record($report->referral, 'cancelled_report_rejected_by_admin_'.$request->user()->id);
                }
                $previous = $report->status;
                if (! $update) {
                    $report->status = $data['status'];
                }
                $review = new ReportReview;
                $review->administrator_id = $request->user()->id;
                $review->previous_status = $previous;
                $review->new_status = $report->status;
                $review->review_note = $update ? 'Municipality update:'."\n".($data['review_note'] ?? 'Supporting evidence attached.') : match ($report->status) {
                    'rejected' => 'Rejection reason:'."\n".$data['rejection_reason'],
                    'resolved' => 'Resolution explicitly confirmed by the administrator using the attached supporting evidence.'
                        .(filled($data['review_note'] ?? null) ? "\n\n".'Remarks:'."\n".$data['review_note'] : ''),
                    'closed' => 'Closure reason (not resolved):'."\n".$data['closure_reason'].(filled($data['follow_up_attempts'] ?? null) ? "\n\n".'Follow-up attempts:'."\n".$data['follow_up_attempts'] : '').(filled($data['review_note'] ?? null) ? "\n\n".'Remarks:'."\n".$data['review_note'] : ''),
                    default => $previous === 'closed' ? 'Reopened — new information:'."\n".$data['reopen_reason'] : ($data['review_note'] ?? null),
                };
                $report->reviews()->save($review);
                foreach ($request->file('supporting_evidence', []) as $file) {
                    ReportEvidenceStorage::store($file, $review, $createdPaths);
                }
                if (! $update) {
                    $report->save();
                }
            });
        } catch (ValidationException $exception) {
            ReportEvidenceStorage::cleanup($createdPaths);
            throw $exception;
        } catch (Throwable $exception) {
            ReportEvidenceStorage::cleanup($createdPaths);
            Log::error('Report review could not be saved.', ['exception_type' => $exception::class]);

            return redirect()->route('admin.reports.show', $pollutionReport)
                ->withErrors(['status' => 'The review or evidence could not be saved. No status change was recorded. Please reselect the files and try again.'])
                ->withInput($request->only(['review_note', 'rejection_reason', 'closure_reason', 'follow_up_attempts', 'reopen_reason']));
        }

        return redirect()->route('admin.reports.show', $pollutionReport)->with('review_saved', true);
    }
}
