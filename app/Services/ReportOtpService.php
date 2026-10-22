<?php

namespace App\Services;

use App\Mail\ReportOtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ReportOtpService
{
    private function emailHash(string $email): string
    {
        return hash_hmac(
            'sha256',
            strtolower(trim($email)),
            config('app.key')
        );
    }

    private function sessionHash(string $sessionId): string
    {
        return hash_hmac(
            'sha256',
            $sessionId,
            config('app.key')
        );
    }

    private function activeSessionKey(
        string $emailHash,
        string $sessionHash
    ): string {
        return hash_hmac(
            'sha256',
            $emailHash.':'.$sessionHash,
            config('app.key')
        );
    }

    public function send(string $email, string $sessionId): void
    {
        $email = strtolower(trim($email));

        $emailHash = $this->emailHash($email);
        $sessionHash = $this->sessionHash($sessionId);
        $activeKey = $this->activeSessionKey(
            $emailHash,
            $sessionHash
        );

        $code = (string) random_int(100000, 999999);

        // Lock the email's cooldown row to serialize OTP creation.
        // Return the exact ID of this request's OTP record.
        $verificationId = DB::transaction(function () use (
            $emailHash,
            $sessionHash,
            $activeKey,
            $code
        ): int {
            DB::table('report_email_cooldowns')->insertOrIgnore([
                'email_hash' => $emailHash,
                'last_report_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('report_email_cooldowns')
                ->where('email_hash', $emailHash)
                ->lockForUpdate()
                ->first();

            // Invalidate older records without deleting them.
            DB::table('report_email_verifications')
                ->where('email_hash', $emailHash)
                ->where('session_hash', $sessionHash)
                ->whereNull('consumed_at')
                ->update([
                    'consumed_at' => now(),
                    'active_session_key' => null,
                    'updated_at' => now(),
                ]);

            return DB::table('report_email_verifications')
                ->insertGetId([
                    'email_hash' => $emailHash,
                    'session_hash' => $sessionHash,
                    'active_session_key' => $activeKey,
                    'otp_hash' => Hash::make($code),
                    'attempts' => 0,
                    'expires_at' => now()->addMinutes(5),
                    'verified_at' => null,
                    'verification_valid_until' => null,
                    'consumed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }, 3);

        try {
            Mail::to($email)->send(new ReportOtpMail($code));
        } catch (\Throwable $exception) {
            // Only invalidate the OTP created by THIS request.
            // A newer OTP cannot be accidentally invalidated.
            DB::table('report_email_verifications')
                ->where('id', $verificationId)
                ->whereNull('consumed_at')
                ->whereNull('verified_at')
                ->update([
                    'consumed_at' => now(),
                    'active_session_key' => null,
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    public function verify(
        string $email,
        string $code,
        string $sessionId
    ): bool {
        $emailHash = $this->emailHash($email);
        $sessionHash = $this->sessionHash($sessionId);

        return DB::transaction(function () use (
            $emailHash,
            $sessionHash,
            $code
        ): bool {
            $verification = DB::table('report_email_verifications')
                ->where('email_hash', $emailHash)
                ->where('session_hash', $sessionHash)
                ->whereNull('consumed_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $verification) {
                return false;
            }

            if ($verification->verified_at !== null) {
                return false;
            }

            if (now()->greaterThanOrEqualTo($verification->expires_at)) {
                return false;
            }

            if ($verification->attempts >= 5) {
                return false;
            }

            if (! Hash::check($code, $verification->otp_hash)) {
                DB::table('report_email_verifications')
                    ->where('id', $verification->id)
                    ->increment('attempts');

                return false;
            }

            DB::table('report_email_verifications')
                ->where('id', $verification->id)
                ->update([
                    'verified_at' => now(),
                    'verification_valid_until' => now()->addMinutes(30),
                    'updated_at' => now(),
                ]);

            return true;
        }, 3);
    }
}
