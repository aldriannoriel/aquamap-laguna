<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewReportRequest;
use App\Models\PollutionReport;
use App\Models\ReportReview;
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
                if ($report->status !== $data['expected_status']
                    || ! in_array($data['status'], PollutionReport::TRANSITIONS[$report->status] ?? [], true)) {
                    throw ValidationException::withMessages(['status' => 'This status change is no longer available. Reload the report and review its current status.'])
                        ->redirectTo(route('admin.reports.show', $pollutionReport));
                }
                $previous = $report->status;
                $report->status = $data['status'];
                $report->save();
                $review = new ReportReview;
                $review->administrator_id = $request->user()->id;
                $review->previous_status = $previous;
                $review->new_status = $report->status;
                $review->review_note = $data['review_note'] ?? null;
                $report->reviews()->save($review);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Report review could not be saved.', ['exception_type' => $exception::class]);

            return redirect()->route('admin.reports.show', $pollutionReport)
                ->withErrors(['status' => 'The review could not be saved. No status change was recorded. Please try again.'])
                ->withInput(['review_note' => $data['review_note'] ?? null]);
        }

        return redirect()->route('admin.reports.show', $pollutionReport)->with('review_saved', true);
    }
}
