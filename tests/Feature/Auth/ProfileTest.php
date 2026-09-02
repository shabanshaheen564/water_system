<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'last_login_at',
                ],
            ]);

        $this->assertEquals('Profile updated successfully', $response->json('message'));
        $this->assertEquals('Updated Name', $response->json('user.name'));
        $this->assertEquals('updated@example.com', $response->json('user.email'));

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('updated@example.com', $user->email);
    }

    public function test_user_can_keep_same_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'same@example.com',
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'same@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $response->json('user.name'));
        $this->assertEquals('same@example.com', $response->json('user.email'));

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('same@example.com', $user->email);
    }

    public function test_duplicate_email_fails(): void
    {
        $user1 = User::factory()->create([
            'name' => 'User One',
            'email' => 'user1@example.com',
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user1->assignRole($role);

        $user2 = User::factory()->create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
        ]);
        $user2->assignRole($role);

        $token = $user1->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile', [
            'name' => 'User One Updated',
            'email' => 'user2@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $user1->refresh();
        $this->assertEquals('User One', $user1->name);
        $this->assertEquals('user1@example.com', $user1->email);
    }

    public function test_unauthenticated_user_cannot_update_profile(): void
    {
        $response = $this->putJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(401);
    }

    public function test_validation_errors(): void
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        // Missing name
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Missing email
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile', [
            'name' => 'Test Name',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Invalid email format
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile', [
            'name' => 'Test Name',
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_sensitive_fields_not_returned(): void
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertStatus(200);

        $userData = $response->json('user');

        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);
        $this->assertArrayNotHasKey('personal_access_tokens', $userData);
        $this->assertArrayNotHasKey('token', $userData);
    }
}