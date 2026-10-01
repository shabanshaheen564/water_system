<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class IdempotencyService
{
    public function replay(User $user, ?string $key, string $endpoint, array $payload): ?JsonResponse
    {
        if (blank($key)) {
            return null;
        }

        $record = IdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->first();

        if (!$record) {
            return null;
        }

        if ($record->endpoint !== $endpoint || $record->request_hash !== $this->hash($payload)) {
            abort(409, 'مفتاح العملية مستخدم لعملية مختلفة.');
        }

        return response()->json($record->response, $record->status_code);
    }

    public function store(User $user, ?string $key, string $endpoint, array $payload, array $response, int $statusCode): void
    {
        if (blank($key)) {
            return;
        }

        IdempotencyKey::query()->firstOrCreate(
            ['user_id' => $user->id, 'key' => $key],
            [
                'endpoint' => $endpoint,
                'request_hash' => $this->hash($payload),
                'response' => $response,
                'status_code' => $statusCode,
            ],
        );
    }

    private function hash(array $payload): string
    {
        unset($payload['idempotency_key']);

        return hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }
}