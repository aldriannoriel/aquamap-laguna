<?php

namespace App\Http\Controllers;

use App\Services\ReportOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ReportOtpController extends Controller
{
    public function send(
        Request $request,
        ReportOtpService $otpService
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Please enter a valid email address.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($validator->validated()['email']));

        $emailHash = hash_hmac(
            'sha256',
            $email,
            config('app.key')
        );

        $minuteKey = 'report-otp-minute:'.$emailHash;
        $hourKey = 'report-otp-hour:'.$emailHash;
        $ipKey = 'report-otp-ip:'.$request->ip();

        if (
            RateLimiter::tooManyAttempts($minuteKey, 1) ||
            RateLimiter::tooManyAttempts($hourKey, 5) ||
            RateLimiter::tooManyAttempts($ipKey, 20)
        ) {
            return response()->json([
                'message' => 'Too many OTP requests. Please try again later.',
            ], 429);
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        try {
            $otpService->send(
                $email,
                $request->session()->getId()
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to send the verification code right now.',
            ], 503);
        }

        return response()->json([
            'message' => 'A verification code has been sent to your email.',
        ]);
    }

    public function verify(
        Request $request,
        ReportOtpService $otpService
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254'],
            'code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Please enter a valid email and 6-digit code.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $verified = $otpService->verify(
            $data['email'],
            (string) $data['code'],
            $request->session()->getId()
        );

        // Reject only when OTP verification actually fails.
        if (! $verified) {
            return response()->json([
                'message' => 'Invalid, expired, or already-used verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Email successfully verified.',
            'verified' => true,
        ]);
    }
}
