<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Mail\Mailables\Address;

class PasswordResetApiController extends Controller
{
    // Configurable TTLs (minutes)
    private const OTP_TTL_MINUTES = 10;
    private const RESET_TOKEN_TTL_MINUTES = 15;

    // Attempt limits
    private const SEND_LIMIT_PER_MIN = 3;     // 3 sends per 1 minute window (per email)
    private const VERIFY_LIMIT_PER_10MIN = 5; // 5 otp checks per 10 minutes (per email)

    /**
     * Step 1: Request OTP to email
     */
    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email','max:255','exists:users,email'],
        ]);

        $email = strtolower($data['email']);

        // Throttle sending
        $sendKey = "pwd:send:{$email}";
        if (RateLimiter::tooManyAttempts($sendKey, self::SEND_LIMIT_PER_MIN)) {
            $seconds = RateLimiter::availableIn($sendKey);
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Try again in '.$seconds.' seconds.'
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }
        RateLimiter::hit($sendKey, 60); // decay 60s

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);
        $hashed = Hash::make($otp);

        // Upsert into password_reset_tokens (hash stored in token)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token'      => $hashed,
                'created_at' => now(), // will be used for expiry
            ]
        );

        // Email the OTP (queued)
        $user = User::where('email', $email)->first();
        $recipient = new Address($user->email, $user->name);
        Mail::to($recipient)->send(new \App\Mail\PasswordResetOtpMail($otp, $user));

        // Generic response (do not leak whether the email exists beyond validation)
        return response()->json([
            'success' => true,
            'message' => 'If that email is registered, a verification code has been sent.',
        ]);
    }

    /**
     * Step 2: Verify OTP, return short-lived reset_token
     */
    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email','max:255'],
            'otp'   => ['required','digits:6'],
        ]);

        $email = strtolower($data['email']);
        $otp   = $data['otp'];

        // Throttle verification attempts
        $verifyKey = "pwd:verify:{$email}";
        if (RateLimiter::tooManyAttempts($verifyKey, self::VERIFY_LIMIT_PER_10MIN)) {
            $seconds = RateLimiter::availableIn($verifyKey);
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Try again in '.$seconds.' seconds.'
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        // Not found or expired
        if (!$row) {
            RateLimiter::hit($verifyKey, 600); // count towards limit
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired code.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $createdAt = CarbonImmutable::parse($row->created_at);
        if ($createdAt->addMinutes(self::OTP_TTL_MINUTES)->isPast()) {
            // Expired – clean up
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            RateLimiter::hit($verifyKey, 600);
            return response()->json([
                'success' => false,
                'message' => 'The verification code has expired. Please request a new one.'
            ], Response::HTTP_GONE);
        }

        // Verify hash match
        if (!Hash::check($otp, $row->token)) {
            RateLimiter::hit($verifyKey, 600);
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // OTP is valid -> issue short-lived reset token and consume OTP
        Cache::put("pwdreset:{$email}", now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES));

        // Consume OTP so it can't be reused
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'OTP verified. Use the reset token to change your password.',
            'data' => [
                'expires_in_seconds' => self::RESET_TOKEN_TTL_MINUTES * 60,
            ],
        ]);
    }

    /**
     * Step 3: Reset password using reset_token
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email'              => ['required','email','max:255','exists:users,email'],
            'confirmed_password' => ['required','string'],
            'new_password'       => ['required','string','min:6'],
        ]);

        // Extra safety: explicit equality check
        if (!hash_equals((string) $data['new_password'], (string) $data['confirmed_password'])) {
            return response()->json([
                'success' => false,
                'message' => 'New password and confirm password do not match.',
            ], \Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email  = strtolower($data['email']);
        $cached = Cache::get("pwdreset:{$email}");
        if (!$cached) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.'
            ], \Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->password = Hash::make($data['new_password']);
        $user->save();

        Cache::forget("pwdreset:{$email}");
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password has been updated successfully.',
        ]);
    }
}
