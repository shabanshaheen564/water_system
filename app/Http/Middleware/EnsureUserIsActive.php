<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->route('login');
        }

        // Force refresh the user from database to get latest is_active status
        $user = $user->fresh();

        if (! $user->is_active) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Account is deactivated.',
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('login')->with('error', 'Account is deactivated.');
        }

        return $next($request);
    }
}