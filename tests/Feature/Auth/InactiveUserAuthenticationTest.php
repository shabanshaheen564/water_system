<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InactiveUserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'last_login_at',
                ],
            ]);

        $this->assertEquals('Login successful', $response->json('message'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Account is deactivated',
            ]);
    }

    public function test_reactivated_user_can_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        // First try to login (should fail)
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403);

        // Now activate the user
        $user->update(['is_active' => true]);

        // Try login again (should succeed)
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Login successful',
            ]);
    }

    public function test_active_user_token_can_access_protected_endpoint(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);
        $this->assertEquals($user->email, $response->json('user.email'));
    }

    public function test_inactive_user_token_cannot_access_protected_endpoint(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        // First deactivate the user
        $user->update(['is_active' => false]);

        // Try to access protected endpoint with the token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Account is deactivated.',
            ]);
    }

    public function test_reactivated_user_token_can_access_again(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $role = Role::where('name', 'Admin')->first();
        $user->assignRole($role);

        $token = $user->createToken('mobile-app')->plainTextToken;

        // Deactivate user
        $user->update(['is_active' => false]);

        // Try with token (should fail)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(403);

        // Reactivate user
        $user->update(['is_active' => true]);

        // Try again with same token (should work now)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);
        $this->assertEquals($user->email, $response->json('user.email'));
    }
}