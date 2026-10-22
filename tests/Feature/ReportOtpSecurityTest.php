<?php

namespace Tests\Feature;

use App\Mail\ReportOtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ReportOtpSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withCredentials();

        $this->artisan('migrate', [
            '--database' => 'sqlite',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        Mail::fake();

        RateLimiter::clear(
            'report-otp-minute:'.hash_hmac(
                'sha256',
                'otp-test@example.test',
                config('app.key')
            )
        );
    }

    private function latestOtpCode(): string
    {
        $code = null;

        Mail::assertSent(
            ReportOtpMail::class,
            function ($mail) use (&$code) {
                $code = $mail->code;

                return true;
            }
        );

        $this->assertNotNull($code);

        return (string) $code;
    }

    private function sendOtp(string $email): string
    {
        $response = $this->postJson(route('reports.otp.send'), [
            'email' => $email,
        ]);

        $response->assertOk();

        // Preserve the session cookie for subsequent requests.
        $sessionName = config('session.cookie');
        $sessionCookie = null;

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionName) {
                $sessionCookie = $cookie->getValue();
                break;
            }
        }

        $this->assertNotNull(
            $sessionCookie,
            'OTP send response did not return a session cookie.'
        );

        // Use withUnencryptedCookie because the session cookie returned
        // by the response is already encrypted by EncryptCookies middleware.
        $this->withUnencryptedCookie($sessionName, $sessionCookie);

        Mail::assertSent(ReportOtpMail::class);

        return $this->latestOtpCode();
    }

    private function assertOtpRecordMatches(
        string $email,
        string $code
    ): void {
        $verification = DB::table('report_email_verifications')
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $verification,
            'OTP record was not created.'
        );

        $expectedEmailHash = hash_hmac(
            'sha256',
            strtolower(trim($email)),
            config('app.key')
        );

        $this->assertSame(
            $expectedEmailHash,
            $verification->email_hash,
            'OTP email hash does not match.'
        );

        $this->assertTrue(
            Hash::check($code, $verification->otp_hash),
            'OTP code does not match the stored hash.'
        );

        $this->assertNull(
            $verification->consumed_at,
            'OTP was already consumed.'
        );

        $this->assertNull(
            $verification->verified_at,
            'OTP was already verified.'
        );

        $this->assertFalse(
            now()->greaterThanOrEqualTo($verification->expires_at),
            'OTP expired before verification.'
        );
    }

    public function test_otp_can_be_sent_and_verified(): void
    {
        $email = 'otp-test@example.test';

        $code = $this->sendOtp($email);

        $this->assertDatabaseCount(
            'report_email_verifications',
            1
        );

        $this->assertOtpRecordMatches($email, $code);

        $this->postJson(route('reports.otp.verify'), [
            'email' => $email,
            'code' => $code,
        ])->assertOk()->assertJson([
            'verified' => true,
        ]);

        $verification = DB::table('report_email_verifications')
            ->first();

        $this->assertNotNull($verification->verified_at);

        $this->assertNotNull(
            $verification->verification_valid_until
        );
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $email = 'otp-test@example.test';

        $correctCode = $this->sendOtp($email);

        $wrongCode = $correctCode === '000000'
            ? '111111'
            : '000000';

        $this->postJson(route('reports.otp.verify'), [
            'email' => $email,
            'code' => $wrongCode,
        ])->assertUnprocessable();

        $this->assertDatabaseHas(
            'report_email_verifications',
            [
                'attempts' => 1,
                'verified_at' => null,
            ]
        );
    }

    public function test_otp_expires_after_five_minutes(): void
    {
        $email = 'otp-test@example.test';

        $code = $this->sendOtp($email);

        $this->travel(6)->minutes();

        $this->postJson(route('reports.otp.verify'), [
            'email' => $email,
            'code' => $code,
        ])->assertUnprocessable();
    }

    public function test_otp_cannot_be_verified_twice(): void
    {
        $email = 'otp-test@example.test';

        $code = $this->sendOtp($email);

        $this->postJson(route('reports.otp.verify'), [
            'email' => $email,
            'code' => $code,
        ])->assertOk();

        $this->postJson(route('reports.otp.verify'), [
            'email' => $email,
            'code' => $code,
        ])->assertUnprocessable();
    }

    public function test_sixth_wrong_attempt_is_rejected(): void
    {
        $email = 'otp-test@example.test';

        $correctCode = $this->sendOtp($email);

        $wrongCode = $correctCode === '000000'
            ? '111111'
            : '000000';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('reports.otp.verify'), [
                'email' => $email,
                'code' => $wrongCode,
            ])->assertUnprocessable();
        }

        $this->postJson(route('reports.otp.verify'), [
            'email' => $email,
            'code' => $correctCode,
        ])->assertUnprocessable();

        $this->assertDatabaseHas(
            'report_email_verifications',
            [
                'attempts' => 5,
                'verified_at' => null,
            ]
        );
    }

    public function test_otp_cannot_be_verified_from_different_session(): void
    {
        $email = 'otp-test@example.test';

        $code = $this->sendOtp($email);

        $sessionName = config('session.cookie');
        $originalSessionCookie = $this->unencryptedCookies[$sessionName];

        // Simulate a different browser session by clearing the session cookie.
        $this->unencryptedCookies = [];

        $this->postJson(route('reports.otp.verify'), [
            'email' => $email,
            'code' => $code,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('report_email_verifications', [
            'verified_at' => null,
        ]);

        // Restore original session: verification must succeed.
        $this->withUnencryptedCookie($sessionName, $originalSessionCookie);

        $this->postJson(route('reports.otp.verify'), [
            'email' => $email,
            'code' => $code,
        ])->assertOk()->assertJson([
            'verified' => true,
        ]);
    }
}
