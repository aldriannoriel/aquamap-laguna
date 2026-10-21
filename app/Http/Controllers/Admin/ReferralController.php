<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\PollutionReport;
use App\Models\Referral;
use App\Support\ReferralWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function index(Request $request): View
    {
        $token = $request->session()->get('referral_test_token');
        if (! is_string($token)) {
            $token = (string) Str::uuid();
            $request->session()->put('referral_test_token', $token);
        }

        return view('admin.referrals.index', ['referrals' => Referral::with('events')->latest('id')->paginate(15), 'testToken' => $token, 'ready' => ReferralWorkflow::ready(),
            'waitingJobs' => DB::table('jobs')->where('queue', 'referrals')->whereNull('reserved_at')->count()]);
    }

    public function preview(Request $request, PollutionReport $pollutionReport): View
    {
        $data = $request->validate(['agency_id' => ['required', 'integer', 'exists:agencies,id'], 'include_photo' => ['nullable', 'boolean']]);
        $a = Agency::findOrFail($data['agency_id']);
        abort_unless(ReferralWorkflow::eligible($a, $pollutionReport), 422, 'Choose an active municipality recipient or a verified routing assignment.');
        $photo = $request->boolean('include_photo');

        return view('admin.referrals.preview', ['report' => $pollutionReport, 'agency' => $a, 'details' => ReferralWorkflow::payload($pollutionReport, $photo),
            'includePhoto' => $photo, 'reviewHash' => ReferralWorkflow::reviewHash($pollutionReport, $a, $photo), 'ready' => ReferralWorkflow::ready()]);
    }

    public function store(Request $request, PollutionReport $pollutionReport): RedirectResponse
    {
        $data = $request->validate(['agency_id' => ['required', 'integer', 'exists:agencies,id'], 'include_photo' => ['required', 'boolean'],
            'review_hash' => ['required', 'string', 'size:64'], 'approved' => ['accepted']]);
        $r = ReferralWorkflow::enqueue($pollutionReport, Agency::findOrFail($data['agency_id']), $request->user(), $request->boolean('include_photo'), $data['review_hash']);

        $message = match ($r->status) {
            'accepted' => 'Referral email sent successfully (accepted by SMTP). Inbox receipt is unverified.',
            'queued' => 'Referral queued for sending.',
            default => 'Referral already recorded: '.$r->label().'. Check its delivery history.',
        };

        return to_route('admin.referrals.index')->with('referral_saved', $message);
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['request_token' => ['required', 'uuid'], 'approved' => ['accepted']]);
        abort_unless(hash_equals((string) $request->session()->get('referral_test_token', ''), $data['request_token']), 419);
        $r = ReferralWorkflow::test($request->user(), $data['request_token']);
        $request->session()->forget('referral_test_token');

        return to_route('admin.referrals.index')->with('referral_saved', 'Live test '.$r->reference.' queued to '.Referral::TEST_RECIPIENT.'. Check the inbox after the worker runs.');
    }

    public function retry(Request $request, Referral $referral): RedirectResponse
    {
        $request->validate(['approved' => ['accepted']]);
        ReferralWorkflow::retry($referral);

        return to_route('admin.referrals.index')->with('referral_saved', 'Safe pre-send failure queued for retry.');
    }

    public function confirm(Request $request, Referral $referral): RedirectResponse
    {
        $data = $request->validate(['approved' => ['accepted'], 'confirmation_reference' => ['required', 'string']]);
        abort_unless($referral->pollution_report_id === null && $referral->status === 'accepted' && hash_equals($referral->reference, $data['confirmation_reference']), 422);
        $referral->forceFill(['inbox_confirmed_at' => now(), 'inbox_confirmed_by' => $request->user()->id])->save();
        ReferralWorkflow::record($referral, 'inbox_receipt_confirmed_by_administrator');

        return to_route('admin.referrals.index')->with('referral_saved', 'Inbox receipt recorded as administrator-confirmed. This does not establish agency action.');
    }
}
