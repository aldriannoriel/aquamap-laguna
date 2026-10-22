<?php

namespace Tests;

use DateTimeInterface;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();

        if (! $app->runningUnitTests()
            || $connection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Tests require SQLite :memory:; no persistent database may be used.');
        }

        return $app;
    }

    /**
     * Simulate a legitimately verified email session for citizen reporting tests.
     */
    protected function verifyEmailSession(string $email = 'citizen@example.test', ?DateTimeInterface $validUntil = null): string
    {
        $this->withCredentials();
        $sessionName = config('session.cookie');

        if (! isset($this->unencryptedCookies[$sessionName])) {
            $response = $this->get(route('reports.create'));
            foreach ($response->headers->getCookies() as $cookie) {
                if ($cookie->getName() === $sessionName) {
                    $this->withUnencryptedCookie($sessionName, $cookie->getValue());
                    break;
                }
            }
        }

        $encryptedCookie = $this->unencryptedCookies[$sessionName] ?? null;
        if ($encryptedCookie) {
            $decrypted = app('encrypter')->decrypt($encryptedCookie, false);
            $prefix = CookieValuePrefix::create($sessionName, app('encrypter')->getKey());
            $sessionId = str_starts_with($decrypted, $prefix) ? substr($decrypted, strlen($prefix)) : $decrypted;
        } else {
            $sessionId = session()->getId();
        }

        $email = strtolower(trim($email));
        $emailHash = hash_hmac('sha256', $email, config('app.key'));
        $sessionHash = hash_hmac('sha256', $sessionId, config('app.key'));
        $activeKey = hash_hmac('sha256', $emailHash.':'.$sessionHash, config('app.key'));

        DB::table('report_email_verifications')
            ->where('email_hash', $emailHash)
            ->where('session_hash', $sessionHash)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'active_session_key' => null,
                'updated_at' => now(),
            ]);

        DB::table('report_email_verifications')->insert([
            'email_hash' => $emailHash,
            'session_hash' => $sessionHash,
            'active_session_key' => $activeKey,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_valid_until' => $validUntil ?? now()->addMinutes(30),
            'consumed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $email;
    }
}
