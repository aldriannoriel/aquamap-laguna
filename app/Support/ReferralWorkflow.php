<?php

namespace App\Support;

use App\Jobs\SendReferral;
use App\Models\Agency;
use App\Models\PollutionReport;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralWorkflow
{
    public static function ready(): bool
    {
        $mailer = config('referrals.mailer');

        return config('mail.mailers.'.$mailer.'.transport') === 'smtp'
            && filled(config('mail.mailers.'.$mailer.'.username')) && filled(config('mail.mailers.'.$mailer.'.password'))
            && ! in_array(config('mail.mailers.'.$mailer.'.host'), [null, '', 'localhost', '127.0.0.1'], true)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && ! str_ends_with(config('mail.from.address', ''), '@example.com');
    }

    public static function assertReady(): void
    {
        if (! self::ready()) {
            throw ValidationException::withMessages(['delivery' => 'Configure a verified SMTP sender first. No email was sent.']);
        }
    }

    public static function payload(PollutionReport $report, bool $includePhoto): array
    {
        $report->loadMissing('waterBody');
        abort_unless($report->waterBody()->supported()->exists(), 422, 'Only supported water bodies may be referred.');
        $photo = null;
        $hash = null;
        if ($includePhoto) {
            $photo = $report->photo_path;
            if (! is_string($photo) || ! preg_match('/\A[A-Za-z0-9_-]+\.(jpe?g|png|webp)\z/i', $photo)
                || ! Storage::disk('pollution-reports')->exists($photo)) {
                throw ValidationException::withMessages(['include_photo' => 'A readable private supporting photo is required for attachment approval.']);
            }
            $hash = hash('sha256', Storage::disk('pollution-reports')->get($photo));
        }

        $selection = MunicipalityRouting::selection($report);

        return ['test' => false, 'report_reference' => 'AQ-'.$report->id, 'water_body' => $report->waterBody->name,
            'incident_municipality' => $selection['municipality'] ?? null, 'incident_routing' => $selection,
            'municipality' => $report->waterBody->municipality, 'concern' => PollutionReport::TYPES[$report->pollution_type] ?? 'Other concern',
            'description' => $report->description, 'observed_on' => $report->observed_on?->format('Y-m-d'),
            'submitted_at' => $report->created_at->toIso8601String(), 'latitude' => $report->latitude, 'longitude' => $report->longitude,
            'landmark' => $report->landmark, 'location_accuracy_m' => $report->location_accuracy_m,
            'photo_path' => $photo, 'photo_sha256' => $hash];
    }

    public static function recipient(Agency $agency): array
    {
        return $agency->only(['name', 'email', 'municipality', 'barangay', 'verification_status']);
    }

    public static function eligible(Agency $agency, PollutionReport $report): bool
    {
        return $agency->active && MunicipalityRouting::exact($agency->municipality) !== null
            && filter_var($agency->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function reviewHash(PollutionReport $report, Agency $agency, bool $photo): string
    {
        return self::fingerprint(self::payload($report, $photo), self::recipient($agency));
    }

    public static function fingerprint(array $payload, array $recipient): string
    {
        return hash('sha256', json_encode([$payload, $recipient], JSON_THROW_ON_ERROR));
    }

    public static function existingHash(Referral $referral): string
    {
        return hash('sha256', json_encode([$referral->reference, $referral->payload, $referral->recipient_snapshot, $referral->include_photo,
            $referral->report ? MunicipalityRouting::selection($referral->report) : null], JSON_THROW_ON_ERROR));
    }

    public static function assertIncidentRecipient(PollutionReport $report, Agency $agency): void
    {
        $selection = MunicipalityRouting::selection($report);
        if (! self::eligible($agency, $report) || ! $selection || $selection['agency_id'] !== $agency->id || $selection['municipality'] !== $agency->municipality) {
            throw ValidationException::withMessages(['agency_id' => 'Save a valid incident municipality selection before sending.']);
        }
    }

    public static function record(Referral $referral, string $event): void
    {
        ReferralEvent::forceCreate(['referral_id' => $referral->id, 'event' => $event, 'created_at' => now()]);
    }

    public static function sendNow(PollutionReport $report, Agency $agency, User $admin, bool $photo, string $reviewHash): Referral
    {
        $referral = self::enqueue($report, $agency, $admin, $photo, $reviewHash, synchronous: true);
        if ($referral->status === 'queued') {
            (new SendReferral($referral->id))->handle($admin->id);
        }

        return $referral->refresh();
    }

    public static function enqueue(PollutionReport $report, Agency $agency, User $admin, bool $photo, string $reviewHash, bool $synchronous = false): Referral
    {
        self::assertReady();

        return DB::transaction(function () use ($report, $agency, $admin, $photo, $reviewHash, $synchronous): Referral {
            $report = PollutionReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $existing = Referral::where('pollution_report_id', $report->id)->first();
            if ($existing) {
                if ($existing->status === 'queued' && ($synchronous || ! $existing->deliveryApproved())) {
                    $agency = Agency::whereKey($agency->id)->lockForUpdate()->firstOrFail();
                    self::assertIncidentRecipient($report, $agency);
                    abort_unless(in_array($report->status, ['pending', 'verified'], true), 422, 'This report is closed and cannot be sent.');
                    abort_unless($existing->attempts === 0 && $existing->sending_at === null && $existing->sent_at === null, 409, 'Review the prior delivery attempt before approving again.');
                    abort_unless(hash_equals(self::existingHash($existing), $reviewHash), 409, 'Review the preserved approval snapshot before sending.');
                    if (! $existing->deliveryApproved()) {
                        self::record($existing, Referral::DELIVERY_APPROVAL);
                    }
                    self::record($existing, 'municipality_delivery_confirmed_by_admin_'.$admin->id);
                    if (! $synchronous) {
                        Queue::connection('referrals')->push(new SendReferral($existing->id), '', config('referrals.queue'));
                    }
                }

                return $existing;
            }
            abort_unless(in_array($report->status, ['pending', 'verified'], true), 422, 'This report is closed and cannot be sent.');
            $agency = Agency::whereKey($agency->id)->lockForUpdate()->firstOrFail();
            self::assertIncidentRecipient($report, $agency);
            abort_unless(self::eligible($agency, $report), 422, 'Choose an active municipality recipient or a verified routing assignment.');
            abort_unless(hash_equals(self::reviewHash($report, $agency, $photo), $reviewHash), 409, 'The report or recipient changed. Preview the email again.');
            if ($report->status === 'pending' && ! $synchronous) {
                $report->forceFill(['status' => 'verified'])->save();
                $review = new ReportReview;
                $review->forceFill(['administrator_id' => $admin->id, 'previous_status' => 'pending',
                    'new_status' => 'verified', 'review_note' => 'Approved for municipality referral.']);
                $report->reviews()->save($review);
            }
            $r = Referral::forceCreate(['reference' => (string) Str::uuid(), 'dedupe_key' => 'report:'.$report->id,
                'pollution_report_id' => $report->id, 'agency_id' => $agency->id, 'approved_by' => $admin->id, 'approved_at' => now(),
                'recipient_snapshot' => self::recipient($agency), 'payload' => self::payload($report, $photo), 'include_photo' => $photo, 'status' => 'queued', 'queued_at' => now()]);
            self::record($r, $synchronous ? 'direct_delivery_requested_by_admin_'.$admin->id : 'admin_approved_and_queued');
            self::record($r, Referral::DELIVERY_APPROVAL);
            if (! $synchronous) {
                Queue::connection('referrals')->push(new SendReferral($r->id), '', config('referrals.queue'));
            }

            return $r;
        });
    }

    public static function retry(Referral $referral, bool $synchronous = false, ?User $admin = null): void
    {
        self::assertReady();
        DB::transaction(function () use ($referral, $synchronous, $admin): void {
            $report = PollutionReport::whereKey($referral->pollution_report_id)->lockForUpdate()->first();
            abort_unless($report && in_array($report->status, ['pending', 'verified'], true), 409, 'This report cannot be sent.');
            $r = Referral::whereKey($referral->id)->lockForUpdate()->firstOrFail();
            abort_unless($r->retryable(), 409, 'This outcome cannot be retried safely. Check the provider before any resend.');
            abort_unless($r->pollution_report_id !== null, 409, 'Legacy diagnostic messages are retained without sending.');
            if (! $r->deliveryApproved()) {
                self::record($r, Referral::DELIVERY_APPROVAL);
            }
            $r->forceFill(['status' => 'queued', 'claim_token' => null, 'sending_at' => null, 'error_code' => null, 'failed_at' => null, 'queued_at' => now()])->save();
            self::record($r, $synchronous ? 'admin_safe_retry_requested_by_admin_'.($admin?->id ?? $r->approved_by) : 'admin_safe_retry_queued');
            if (! $synchronous) {
                Queue::connection('referrals')->push(new SendReferral($r->id), '', config('referrals.queue'));
            }
        });
        if ($synchronous) {
            (new SendReferral($referral->id))->handle($admin?->id);
        }
    }

    public static function attachment(array $payload): string
    {
        $path = $payload['photo_path'] ?? null;
        if (! is_string($path) || ! preg_match('/\A[A-Za-z0-9_-]+\.(jpe?g|png|webp)\z/i', $path)) {
            throw new \RuntimeException('Invalid private evidence.');
        }
        $bytes = Storage::disk('pollution-reports')->get($path);
        $size = getimagesizefromstring($bytes);
        if (! $size || strlen($bytes) > 5 * 1024 * 1024 || $size[0] * $size[1] > 20000000 || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || ! hash_equals($payload['photo_sha256'], hash('sha256', $bytes))) {
            throw new \RuntimeException('Evidence changed or cannot be attached safely.');
        }
        $image = imagecreatefromstring($bytes);
        if (! $image) {
            throw new \RuntimeException('Evidence decode failed.');
        }
        ob_start();
        imagejpeg($image, null, 85);
        $safe = ob_get_clean();
        imagedestroy($image);
        if (! is_string($safe) || $safe === '' || strlen($safe) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Evidence encoding failed.');
        }

        return $safe;
    }
}
