<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_authenticated_user_with_users_view_can_view_user(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $targetUser = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
        ]);
        $targetUser->assignRole($role);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'is_active',
                'last_login_at',
                'created_at',
                'updated_at',
                'roles',
            ]);

        $this->assertEquals('Target User', $response->json('name'));
        $this->assertEquals('target@example.com', $response->json('email'));
        $this->assertEquals('Engineer', $response->json('roles.0.name'));
    }

    public function test_user_without_users_view_cannot_view_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/users/{$targetUser->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_receives_401(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->getJson("/api/users/{$targetUser->id}");

        $response->assertStatus(401);
    }

    public function test_nonexistent_user_returns_404(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users/999999');

        $response->assertStatus(404);
    }

    public function test_sensitive_fields_are_not_returned(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200);

        $userData = $response->json();

        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);
        $this->assertArrayNotHasKey('personal_access_tokens', $userData);
        $this->assertArrayNotHasKey('token', $userData);
        $this->assertArrayNotHasKey('plain_text_token', $userData);
    }

    public function test_roles_are_returned(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($role);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200);

        $this->assertIsArray($response->json('roles'));
        $this->assertCount(1, $response->json('roles'));
        $this->assertEquals('Engineer', $response->json('roles.0.name'));
        $this->assertEquals($role->id, $response->json('roles.0.id'));
    }

    public function test_multiple_roles_are_returned(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        $engineerRole = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $viewerRole = \Spatie\Permission\Models\Role::where('name', 'Viewer')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole([$engineerRole, $viewerRole]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200);

        $roles = $response->json('roles');
        $this->assertCount(2, $roles);

        $roleNames = collect($roles)->pluck('name')->toArray();
        $this->assertContains('Engineer', $roleNames);
        $this->assertContains('Viewer', $roleNames);
    }
}