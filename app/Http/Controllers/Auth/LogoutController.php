<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LogoutController extends Controller
{
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $tokenHash = $token->token;
        $token->delete();

        Cache::forget("sanctum.token.{$tokenHash}");

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}