<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Spatie\Permission\Exceptions\UnauthorizedException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof UnauthorizedException) {
            $user = $request->user();

            $redirect = $user ? $this->roleDashboardUrl($user) : route('ext.login');
            $message  = 'You are not allowed to access that route. Redirected to your dashboard.';

            // For API (mobile / ajax / api routes)
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success'     => false,
                    'message'     => $message,
                    'redirect_to' => $redirect,
                ], 403);
            }

            // For normal web (only works if user is session-auth)
            return redirect($redirect)->with('error', $message);
        }

        return parent::render($request, $e);
    }

    private function roleDashboardUrl($user): string
    {
        // Match your exact role names (Spatie role names)
        if ($user->hasRole('Super Admin')) return route('dashboard.superadmin');
        if ($user->hasRole('Admin') || $user->hasRole('PO')) return route('dashboard.admin');
        return route('dashboard.user');
    }

}
