<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFrontendToken
{
     public function handle(Request $request, Closure $next)
    {
        // 1) Public routes – never block
        if ($request->is('ext/login') || $request->is('api/auth/login') || $request->is('/')) {
            return $next($request);
        }

        // 2) If it's an API call, we REQUIRE bearer
        if ($request->is('api/*')) {
            $bearer = $request->bearerToken();

            if (! $bearer) {
                // pure API → 401 JSON
                return response()->json([
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // if you want, validate token here…
            return $next($request);
        }

        // 3) If it's a WEB GET (Blade), DO NOT redirect.
        // Let the page load; your JS will read localStorage and decide.
        return $next($request);
    }
}