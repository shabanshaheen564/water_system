<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use ReflectionClass;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logout successful',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile-app',
        ]);
    }

    public function test_token_cannot_be_used_after_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        // Debug: check if token can be found after logout
        $foundToken = PersonalAccessToken::findToken($token);
        $this->assertNull($foundToken, 'Token should not be findable after logout');

        // Clear the sanctum guard's user cache via reflection
        $guard = app('auth')->guard('sanctum');
        $reflection = new ReflectionClass($guard);
        $property = $reflection->getProperty('user');
        $property->setAccessible(true);
        $property->setValue($guard, null);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    public function test_other_tokens_remain_valid(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('mobile-app-1')->plainTextToken;
        $token2 = $user->createToken('mobile-app-2')->plainTextToken;

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile-app-1',
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile-app-2',
        ]);

        // Logout using token1
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/logout');

        // Token1 should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile-app-1',
        ]);

        // Token2 should still exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile-app-2',
        ]);

        // Token2 should still work
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->getJson('/api/user');

        $response->assertStatus(200);
    }
}