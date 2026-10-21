<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewReportRequest;
use App\Models\PollutionReport;
use App\Models\ReportReview;
use App\Support\ReferralWorkflow;
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
        try {
            DB::transaction(function () use ($request, $pollutionReport, $data): void {
                $report = PollutionReport::query()->lockForUpdate()->findOrFail($pollutionReport->id);
                $report->setRelation('referral', $report->referral()->lockForUpdate()->first());
                if ($report->status !== $data['expected_status']
                    || ! in_array($data['status'], $report->availableTransitions(), true)) {
                    throw ValidationException::withMessages(['status' => 'This status change is no longer available. Reload the report and review its current status.'])
                        ->redirectTo(route('admin.reports.show', $pollutionReport));
                }
                if ($data['status'] === 'rejected' && $report->referral && $report->referral->status !== 'cancelled') {
                    $report->referral->forceFill(['status' => 'cancelled', 'claim_token' => null])->save();
                    ReferralWorkflow::record($report->referral, 'cancelled_report_rejected_by_admin_'.$request->user()->id);
                }
                $previous = $report->status;
                $report->status = $data['status'];
                $report->save();
                $review = new ReportReview;
                $review->administrator_id = $request->user()->id;
                $review->previous_status = $previous;
                $review->new_status = $report->status;
                $review->review_note = match ($report->status) {
                    'rejected' => 'Rejection reason:'."\n".$data['rejection_reason'],
                    'resolved' => 'Remarks:'."\n".$data['review_note']."\n\n".'Documented resolution basis / supporting evidence:'."\n".$data['resolution_basis'],
                    'closed' => 'Closure reason (not resolved):'."\n".$data['closure_reason'].(filled($data['follow_up_attempts'] ?? null) ? "\n\n".'Follow-up attempts:'."\n".$data['follow_up_attempts'] : '').(filled($data['review_note'] ?? null) ? "\n\n".'Remarks:'."\n".$data['review_note'] : ''),
                    default => $previous === 'closed' ? 'Reopened — new information:'."\n".$data['reopen_reason'] : ($data['review_note'] ?? null),
                };
                $report->reviews()->save($review);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Report review could not be saved.', ['exception_type' => $exception::class]);

            return redirect()->route('admin.reports.show', $pollutionReport)
                ->withErrors(['status' => 'The review could not be saved. No status change was recorded. Please try again.'])
                ->withInput($request->only(['review_note', 'rejection_reason', 'resolution_basis', 'closure_reason', 'follow_up_attempts', 'reopen_reason']));
        }

        return redirect()->route('admin.reports.show', $pollutionReport)->with('review_saved', true);
    }
}
