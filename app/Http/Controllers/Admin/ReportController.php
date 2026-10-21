<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\Agency;
use App\Models\PollutionReport;
use App\Models\Referral;
use App\Models\WaterBody;
use App\Support\MunicipalityRouting;
use App\Support\ReferralWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $query = PollutionReport::query()->with(['waterBody:id,name', 'referral']);
        $queue = $filters['queue'] ?? 'all';
        if ($queue === 'action') {
            $query->actionRequired();
        } elseif ($queue === 'processed') {
            $query->processed();
        }
        foreach (['lake' => 'water_body_id', 'type' => 'pollution_type', 'status' => 'status'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (isset($filters['q'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('description', 'like', '%'.$filters['q'].'%')
                    ->orWhereHas('waterBody', fn (Builder $lakes) => $lakes->where('name', 'like', '%'.$filters['q'].'%'));
            });
        }
        if (isset($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return view('admin.reports.index', [
            'reports' => $query->latest('id')->paginate(15)->appends($filters),
            'legacyDeliveries' => Referral::with('events')->whereNull('pollution_report_id')->latest('id')->paginate(10, ['*'], 'delivery_page')->withQueryString(),
            'filters' => $filters, 'queue' => $queue, 'lakes' => WaterBody::query()->supported()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(PollutionReport $pollutionReport): View
    {
        $pollutionReport->load(['waterBody:id,name,municipality', 'reviews.administrator:id,name']);

        $recipients = Agency::with('waterBodies:id')->where('active', true)->orderBy('municipality')->get()
            ->filter(fn (Agency $agency): bool => ReferralWorkflow::eligible($agency, $pollutionReport));
        $matches = $recipients->where('municipality', MunicipalityRouting::exact($pollutionReport->waterBody->municipality));
        $referral = $pollutionReport->referral?->load('events');
        $options = [];
        if ($referral && $referral->status === 'queued' && ! $referral->deliveryApproved() && in_array($pollutionReport->status, ['pending', 'verified'], true)) {
            $options[(string) $referral->agency_id] = [($referral->include_photo ? '1' : '0') => ['hash' => ReferralWorkflow::existingHash($referral),
                'text' => view('mail.referral-text', ['details' => $referral->payload, 'reference' => $referral->reference])->render(),
                'recipient' => $referral->recipient_snapshot['name'].' / '.$referral->recipient_snapshot['email']]];
        } elseif (! $referral && $recipients->isNotEmpty() && in_array($pollutionReport->status, ['pending', 'verified'], true)
            && $pollutionReport->waterBody()->supported()->exists()) {
            $payloads = [0 => ReferralWorkflow::payload($pollutionReport, false)];
            if (is_string($pollutionReport->photo_path) && preg_match('/\A[A-Za-z0-9_-]+\.(jpe?g|png|webp)\z/i', $pollutionReport->photo_path)
                && Storage::disk('pollution-reports')->exists($pollutionReport->photo_path)) {
                $payloads[1] = ReferralWorkflow::payload($pollutionReport, true);
            }
            foreach ($recipients as $recipient) {
                foreach ($payloads as $photo => $details) {
                    $options[(string) $recipient->id][(string) $photo] = [
                        'hash' => ReferralWorkflow::fingerprint($details, ReferralWorkflow::recipient($recipient)),
                        'text' => view('mail.referral-text', ['details' => $details, 'reference' => 'Assigned on approval'])->render(),
                        'recipient' => $recipient->municipality.' / '.$recipient->email];
                }
            }
        }

        return view('admin.reports.show', ['report' => $pollutionReport,
            'recipients' => $recipients,
            'suggestedRecipient' => $referral && ! $referral->deliveryApproved() ? $referral->agency_id : ($matches->count() === 1 ? $matches->first()->id : null),
            'referral' => $referral, 'sendOptions' => $options, 'ready' => ReferralWorkflow::ready(),
        ]);
    }

    public function photo(PollutionReport $pollutionReport): StreamedResponse
    {
        $path = $pollutionReport->photo_path;
        abort_unless(is_string($path) && preg_match('/\A[A-Za-z0-9_-]+\.(jpe?g|png|webp)\z/i', $path), 404);
        $disk = Storage::disk('pollution-reports');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        abort_unless(isset($extensions[$mime]), 404);

        return $disk->response($path, 'private-evidence.'.$extensions[$mime], [
            'Content-Type' => $mime, 'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
