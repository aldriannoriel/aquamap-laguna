<?php

namespace App\Jobs;

use App\Mail\EnvironmentalReferral;
use App\Models\Referral;
use App\Support\ReferralWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\MailFake;
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
        $this->onQueue('referrals');
    }

    public function handle(): void
    {
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
            $fake = app()->runningUnitTests() && Mail::getFacadeRoot() instanceof MailFake;
            if ($sent === null && ! $fake) {
                throw new \RuntimeException('No transport acknowledgement.');
            }
            $id = $sent?->getMessageId();
            if ($id !== null && (! preg_match('/\A[A-Za-z0-9@._<>=+\-]+\z/D', $id) || strlen($id) > 255)) {
                $id = hash('sha256', $id);
            }
            DB::transaction(function () use ($r, $id): void {
                $r = Referral::whereKey($r->id)->lockForUpdate()->firstOrFail();
                $r->forceFill(['status' => 'accepted', 'sent_at' => now(), 'error_code' => null, 'provider_message_id' => $id])->save();
                ReferralWorkflow::record($r, 'transport_accepted');
            });
        } catch (Throwable) {
            Referral::whereKey($r->id)->where('status', 'sending')->update(['status' => 'uncertain', 'error_code' => 'transport_outcome_unknown', 'failed_at' => now()]);
            ReferralWorkflow::record($r, 'transport_outcome_unknown_no_automatic_retry');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $r = Referral::find($this->referralId);
        if ($r && in_array($r->status, ['queued', 'preparing', 'sending'], true)) {
            $r->forceFill(['status' => $r->sending_at ? 'uncertain' : 'failed', 'error_code' => 'worker_interrupted', 'failed_at' => now()])->save();
            ReferralWorkflow::record($r, 'worker_interrupted');
        }
    }
}
