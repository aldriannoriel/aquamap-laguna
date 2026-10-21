<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\PollutionReport;
use App\Models\Referral;
use App\Support\ReferralWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function preview(Request $request, PollutionReport $pollutionReport): View
    {
        $data = $request->validate(['agency_id' => ['required', 'integer', 'exists:agencies,id'], 'include_photo' => ['nullable', 'boolean']]);
        $agency = Agency::findOrFail($data['agency_id']);
        abort_unless(ReferralWorkflow::eligible($agency, $pollutionReport), 422, 'Choose an active municipality recipient or verified routing assignment.');
        $photo = $request->boolean('include_photo');
        $existing = $pollutionReport->referral;

        return view('admin.referrals.preview', ['report' => $pollutionReport, 'agency' => $agency,
            'recipient' => $existing?->recipient_snapshot ?? ReferralWorkflow::recipient($agency),
            'reference' => $existing?->reference ?? 'Assigned on approval',
            'details' => $existing?->payload ?? ReferralWorkflow::payload($pollutionReport, $photo),
            'includePhoto' => $existing?->include_photo ?? $photo,
            'reviewHash' => $existing ? ReferralWorkflow::existingHash($existing) : ReferralWorkflow::reviewHash($pollutionReport, $agency, $photo),
            'ready' => ReferralWorkflow::ready()]);
    }

    public function store(Request $request, PollutionReport $pollutionReport): RedirectResponse
    {
        $data = $request->validate(['agency_id' => ['required', 'integer', 'exists:agencies,id'], 'include_photo' => ['required', 'boolean'],
            'review_hash' => ['required', 'string', 'size:64'], 'approved' => ['accepted']]);
        $referral = ReferralWorkflow::enqueue($pollutionReport, Agency::findOrFail($data['agency_id']), $request->user(),
            $request->boolean('include_photo'), $data['review_hash']);
        $message = $referral->status === 'accepted'
            ? 'Referral email sent successfully (accepted by SMTP). '.($referral->inbox_confirmed_at
                ? 'Receiver inbox receipt was separately confirmed.' : 'Inbox receipt is unverified.')
            : ($referral->status === 'queued' ? 'Referral queued for sending. Waiting for the municipality worker.' : $referral->label().'. Review delivery history.');

        return to_route('admin.reports.show', $pollutionReport)->with('referral_saved', $message);
    }

    public function status(PollutionReport $pollutionReport): JsonResponse
    {
        $referral = $pollutionReport->referral;

        return response()->json(['status' => $referral?->status, 'label' => $referral?->label()]);
    }

    public function retry(Request $request, PollutionReport $pollutionReport, Referral $referral): RedirectResponse
    {
        abort_unless($referral->pollution_report_id === $pollutionReport->id, 404);
        $request->validate(['approved' => ['accepted']]);
        ReferralWorkflow::retry($referral);

        return to_route('admin.reports.show', $pollutionReport)->with('referral_saved', 'Retry queued. Email is not marked Sent until SMTP accepts it.');
    }

    public function confirm(Request $request, PollutionReport $pollutionReport, Referral $referral): RedirectResponse
    {
        abort_unless($referral->pollution_report_id === $pollutionReport->id, 404);
        $data = $request->validate(['approved' => ['accepted'], 'confirmation_reference' => ['required', 'string']]);
        DB::transaction(function () use ($referral, $data, $request): void {
            $record = Referral::whereKey($referral->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'accepted' && hash_equals($record->reference, $data['confirmation_reference']), 422);
            if ($record->inbox_confirmed_at === null) {
                $record->forceFill(['inbox_confirmed_at' => now(), 'inbox_confirmed_by' => $request->user()->id])->save();
                ReferralWorkflow::record($record, 'inbox_receipt_confirmed_by_administrator');
            }
        });

        return to_route('admin.reports.show', $pollutionReport)->with('referral_saved', 'Receiver inbox receipt recorded as administrator-confirmed.');
    }
}
