<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Notice Management System API",
 *      description="API documentation for frontend devs",
 *      @OA\Contact(
 *          email="nadim.hossain193@email.com"
 *      )
 * )
 */

class SanctumAuthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {

    }

        /**
     * Login The User
     * @param Request $request
     * @return User
     */
    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Auth"},
     *     summary="Login",
     *     description="Login with email and password to get API token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="1|abcd1234tokenstring"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized / Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string", example="The email field is required."))
     *             )
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
            'fcm_token' => 'nullable',
        ]);

        // Optional: Throttle login attempts
        if (RateLimiter::tooManyAttempts('login:'.$request->ip(), 5)) {
            return response()->json(['message' => 'Too many attempts.'], 429);
        }

        $user = User::where('email', $request->email)
            // ->with('userhasRole.role')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive. Please contact admin for approval.'
            ], 403);
        }

        // Revoke previous tokens if you want single device login
        // $user->tokens()->delete();

        // store new fcm token
        $user->fcm_token = $request->fcm_token;
        $user->save();

        $token = $user->createToken('API_TOKEN')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ], 200);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255|unique:users,email',
            'password'       => ['required', 'confirmed', Password::min(6)],
            'phone'          => 'nullable|string|max:50',
        ]);

        $data = [
            'name'           => $request->name,
            'email'          => $request->email,
            'password'       => Hash::make($request->password),
            'phone'          => $request->phone,
            'status'         => 'inactive', // Default status is inactive until admin approval
        ];

        $user = User::create($data);


        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Your account is pending admin approval.'
        ], 201);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.'
            ], 404);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetUrl = url('/reset-password') . '?email=' . urlencode($request->email) . '&token=' . $token;

        return response()->json([
            'success' => true,
            'message' => 'Password reset link has been sent to your email.'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset token.'
            ], 400);
        }

        if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'Password reset link has expired. Please request a new one.'
            ], 400);
        }

        if (!Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password reset token.'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully. Please login with your new password.'
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Auth"},
     *     summary="Logout",
     *     description="Revoke the user's API token and logout. Requires authentication.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - if not logged in",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        try {
            $allDevices = $request->input('all_devices', false);

            if ($allDevices) {
                // Logout from all devices
                $request->user()->tokens()->delete();
                $request->user()->fcm_token = null; // Clear FCM token
                $request->user()->save();
            } else {
                // Logout from current device only
                $request->user()->currentAccessToken()->delete();
                $request->user()->fcm_token = null; // Clear FCM token
                $request->user()->save();
            }

            return response()->json([
                'success' => true,
                'message' => $allDevices ? 'Logged out from all devices.' : 'Logged out successfully.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: ' . $th->getMessage()
            ], 500);
        }
    }
}
