<?php

namespace App\Http\Controllers;

use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request, FcmService $fcm): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:android'],
        ]);

        $fcm->registerToken(
            $request->user(),
            $validated['token'],
            $validated['platform'] ?? 'android'
        );

        return response()->json([
            'message' => 'FCM token registered successfully.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ])['token'];

        \DB::table('fcm_tokens')
            ->where('user_id', $request->user()->id)
            ->where('token', $token)
            ->delete();

        return response()->json([
            'message' => 'FCM token removed successfully.',
        ]);
    }
}
