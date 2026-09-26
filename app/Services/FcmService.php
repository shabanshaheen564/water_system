<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private const TOKEN_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function registerToken(User $user, string $token, string $platform = 'android'): void
    {
        DB::table('fcm_tokens')->updateOrInsert(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ): int {
        $tokens = DB::table('fcm_tokens')
            ->where('user_id', $userId)
            ->pluck('token')
            ->all();

        $sent = 0;
        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            $serviceAccount = $this->serviceAccount();
            if ($serviceAccount === null) {
                return false;
            }

            $projectId = config('fcm.project_id') ?: ($serviceAccount['project_id'] ?? null);
            if (!$projectId) {
                return false;
            }

            $accessToken = $this->accessToken($serviceAccount);
            if (!$accessToken) {
                return false;
            }

            $stringData = [];
            foreach ($data as $key => $value) {
                $stringData[(string) $key] = is_scalar($value)
                    ? (string) $value
                    : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => $stringData,
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'channel_id' => 'municipal_operations',
                                    'sound' => 'default',
                                ],
                            ],
                        ],
                    ]
                );

            if ($response->successful()) {
                return true;
            }

            $responseText = $response->body();
            if (
                str_contains($responseText, 'UNREGISTERED') ||
                str_contains($responseText, 'registration-token-not-registered')
            ) {
                DB::table('fcm_tokens')->where('token', $token)->delete();
            }

            Log::warning('FCM send failed.', [
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('FCM send exception.', [
                'message' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function serviceAccount(): ?array
    {
        $encoded = config('fcm.service_account_json_base64');
        if (!$encoded) {
            return null;
        }

        $json = base64_decode($encoded, true);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function accessToken(array $serviceAccount): ?string
    {
        $cacheKey = 'fcm.oauth_access_token.' . sha1((string) ($serviceAccount['client_email'] ?? ''));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($serviceAccount) {
            $privateKey = $serviceAccount['private_key'] ?? null;
            $clientEmail = $serviceAccount['client_email'] ?? null;
            if (!$privateKey || !$clientEmail) {
                return null;
            }

            $now = time();
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ]));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => self::TOKEN_SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header . '.' . $claims;

            if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $assertion = $unsigned . '.' . $this->base64UrlEncode($signature);
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (!$response->successful()) {
                Log::warning('FCM OAuth token request failed.', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
