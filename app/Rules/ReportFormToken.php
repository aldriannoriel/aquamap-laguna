<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Translation\PotentiallyTranslatedString;
use JsonException;

class ReportFormToken implements ValidationRule
{
    public const MINIMUM_MILLISECONDS = 3000;

    public const LIFETIME_MILLISECONDS = 7200000;

    public function __construct(private Request $request) {}

    public static function issue(Request $request): string
    {
        $previous = $request->old('report_form_token');
        if (self::openedAt($previous, $request) !== null) {
            return $previous;
        }

        return Crypt::encryptString(json_encode([
            'purpose' => 'pollution-report',
            'issued_at' => now()->getTimestampMs(),
            'session' => hash('sha256', $request->session()->token()),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $openedAt = self::openedAt($value, $this->request);
        if ($openedAt === null) {
            $fail('This form could not be verified. Please review your entries below and submit again.');
        } elseif (now()->getTimestampMs() - $openedAt < self::MINIMUM_MILLISECONDS) {
            $fail('Please wait at least 3 seconds after opening the form, then submit again. Your entries have been kept.');
        }
    }

    private static function openedAt(mixed $token, Request $request): ?int
    {
        if (! is_string($token) || strlen($token) > 2048) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($payload) || ($payload['purpose'] ?? null) !== 'pollution-report' || ! is_int($payload['issued_at'] ?? null) || ! is_string($payload['session'] ?? null)
            || ! hash_equals(hash('sha256', $request->session()->token()), $payload['session'])) {
            return null;
        }
        $age = now()->getTimestampMs() - $payload['issued_at'];

        return $age >= 0 && $age <= self::LIFETIME_MILLISECONDS ? $payload['issued_at'] : null;
    }
}
