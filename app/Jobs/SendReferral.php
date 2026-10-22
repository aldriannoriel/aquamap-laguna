<?php

namespace App\Jobs;

use App\Mail\EnvironmentalReferral;
use App\Models\PollutionReport;
use App\Models\Referral;
use App\Models\ReportReview;
use App\Support\ReferralWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\MailFake;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SendReferral implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $referralId)
    {
        $this->onConnection('referrals');
        $this->onQueue(config('referrals.queue'));
    }

    public function handle(?int $administratorId = null): void
    {
        // Stored jobs are preserved but require a new explicit synchronous admin action.
        if ($this->job !== null) {
            $this->release(86400);

            return;
        }
        $approval = Referral::find($this->referralId);
        if ($approval && ! $approval->deliveryApproved()) {
            if ($this->job !== null) {
                $this->release(86400);
            }

            return;
        }
        $token = (string) Str::uuid();
        $r = DB::transaction(function () use ($token): ?Referral {
            $r = Referral::whereKey($this->referralId)->lockForUpdate()->first();
            if (! $r || ($r->status !== 'queued' && ! ($r->status === 'preparing' && $r->updated_at->lt(now()->subMinutes(2))))) {
                return null;
            }
            $r->forceFill(['status' => 'preparing', 'claim_token' => $token, 'attempts' => $r->attempts + 1])->save();
            ReferralWorkflow::record($r, 'preparing');

            return $r;
        });
        if (! $r) {
            return;
        }
        try {
            // Windows counts SMTP socket waits toward PHP's request execution limit.
            $limit = (int) ini_get('max_execution_time');
            if ($limit > 0 && $limit < 120 && (! function_exists('set_time_limit') || ! set_time_limit(120))) {
                throw new \RuntimeException('The send request execution allowance could not be set.');
            }
            ReferralWorkflow::assertReady();
            $bytes = $r->include_photo ? ReferralWorkflow::attachment($r->payload) : null;
            $mail = new EnvironmentalReferral($r->payload, $r->reference, $bytes);
            $mail->render();
        } catch (Throwable) {
            DB::transaction(function () use ($token): void {
                $r = Referral::whereKey($this->referralId)->lockForUpdate()->firstOrFail();
                if ($r->claim_token !== $token || $r->status !== 'preparing') {
                    return;
                }
                $retry = $r->attempts < 3 && $this->job !== null;
                $r->forceFill(['status' => $retry ? 'queued' : 'failed', 'error_code' => 'preflight_failed', 'failed_at' => $retry ? null : now()])->save();
                ReferralWorkflow::record($r, $retry ? 'safe_preflight_retry' : 'preflight_failed');
                if ($retry) {
                    $this->release($this->backoff[min($r->attempts - 1, 2)]);
                }
            });

            return;
        }
        $claimed = Referral::whereKey($r->id)->where('status', 'preparing')->where('claim_token', $token)->update(['status' => 'sending', 'sending_at' => now()]);
        if (! $claimed) {
            return;
        }
        try {
            $sent = Mail::mailer(config('referrals.mailer'))->to($r->recipient_snapshot['email'])->send($mail);
            $fake = Mail::getFacadeRoot() instanceof MailFake;
            if ($sent === null && ! $fake) {
                throw new \RuntimeException('No transport acknowledgement.');
            }
            $id = $sent?->getMessageId();
            if ($id !== null && (! preg_match('/\A[A-Za-z0-9@._<>=+\-]+\z/D', $id) || strlen($id) > 255)) {
                $id = hash('sha256', $id);
            }
            DB::transaction(function () use ($r, $id, $administratorId, $token): void {
                $report = PollutionReport::whereKey($r->pollution_report_id)->lockForUpdate()->firstOrFail();
                $r = Referral::whereKey($r->id)->lockForUpdate()->firstOrFail();
                if ($r->claim_token !== $token || ! in_array($r->status, ['sending', 'uncertain'], true) || $r->sent_at !== null) {
                    return;
                }
                $r->forceFill(['status' => 'accepted', 'sent_at' => now(), 'error_code' => null, 'provider_message_id' => $id])->save();
                ReferralWorkflow::record($r, 'transport_accepted');
                if ($report->status === 'pending') {
                    $report->forceFill(['status' => 'verified'])->save();
                    $review = new ReportReview;
                    $review->forceFill(['administrator_id' => $administratorId ?? $r->approved_by, 'previous_status' => 'pending', 'new_status' => 'verified',
                        'review_note' => 'Forwarded after SMTP transport acceptance; resolution remains pending.']);
                    $report->reviews()->save($review);
                }
            });
        } catch (Throwable $exception) {
            $code = null;
            if ($exception instanceof TransportExceptionInterface
                && preg_match('/Expected response code "[0-9,\/ ]+" but got code "([45][0-9]{2})"/', $exception->getMessage(), $matches)) {
                $code = in_array($matches[1], ['530', '534', '535'], true) ? 'smtp_auth_rejected' : 'smtp_rejected';
            }
            if ($exception instanceof TransportExceptionInterface && preg_match('/\A(?:Connection could not be established with host |Unable to connect with STARTTLS\.|TLS required but neither TLS or STARTTLS are in use\.)/', $exception->getMessage())) {
                $code = 'smtp_connection_failed';
            }
            DB::transaction(function () use ($r, $code): void {
                $current = Referral::whereKey($r->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== 'sending') {
                    return;
                }
                $current->forceFill(['status' => $code ? 'failed' : 'uncertain', 'error_code' => $code ?? 'transport_outcome_unknown', 'failed_at' => now()])->save();
                ReferralWorkflow::record($current, $code ?? 'transport_outcome_unknown_no_automatic_retry');
            });
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function (): void {
            $r = Referral::whereKey($this->referralId)->lockForUpdate()->first();
            if ($r && in_array($r->status, ['queued', 'preparing', 'sending'], true)) {
                $r->forceFill(['status' => $r->sending_at ? 'uncertain' : 'failed', 'error_code' => 'worker_interrupted', 'failed_at' => now()])->save();
                ReferralWorkflow::record($r, 'worker_interrupted');
            }
        });
    }
}
