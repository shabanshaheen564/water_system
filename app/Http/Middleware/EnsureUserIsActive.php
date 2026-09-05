<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Force refresh the user from database to get latest is_active status
        $user = $user->fresh();

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Account is deactivated.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}