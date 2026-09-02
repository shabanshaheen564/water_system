<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_authenticated_user_with_users_view_can_list_users(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        User::factory()->count(5)->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'last_login_at',
                        'created_at',
                        'updated_at',
                        'roles',
                    ],
                ],
                'links',
                'meta',
            ]);

        // 5 created + 1 admin = 6 minimum (additional users from seeders may exist)
        $this->assertGreaterThanOrEqual(6, count($response->json('data')));
    }

    public function test_user_without_users_view_cannot_list_users(): void
    {
        $user = User::factory()->create();
        // No users.view permission

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_receives_401(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    public function test_list_is_paginated(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        User::factory()->count(20)->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users');

        $response->assertStatus(200);
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('links', $response->json());
        $this->assertLessThanOrEqual(15, count($response->json('data')));
    }

    public function test_user_fields_are_returned_correctly(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_active' => true,
        ]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users');

        $response->assertStatus(200);

        $users = $response->json('data');
        $found = collect($users)->firstWhere('email', 'test@example.com');

        $this->assertNotNull($found);
        $this->assertEquals('Test User', $found['name']);
        $this->assertEquals('test@example.com', $found['email']);
        $this->assertTrue($found['is_active']);
        $this->assertNotNull($found['created_at']);
        $this->assertNotNull($found['updated_at']);
    }

    public function test_roles_are_returned(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::where('name', 'Engineer')->first();
        $user = User::factory()->create();
        $user->assignRole($role);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users');

        $response->assertStatus(200);

        $users = $response->json('data');
        $found = collect($users)->firstWhere('email', $user->email);

        $this->assertNotNull($found);
        $this->assertIsArray($found['roles']);
        $this->assertCount(1, $found['roles']);
        $this->assertEquals('Engineer', $found['roles'][0]['name']);
        $this->assertEquals($role->id, $found['roles'][0]['id']);
    }

    public function test_sensitive_fields_are_not_returned(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users');

        $response->assertStatus(200);

        $users = $response->json('data');

        foreach ($users as $user) {
            $this->assertArrayNotHasKey('password', $user);
            $this->assertArrayNotHasKey('remember_token', $user);
            $this->assertArrayNotHasKey('personal_access_tokens', $user);
            $this->assertArrayNotHasKey('token', $user);
            $this->assertArrayNotHasKey('plain_text_token', $user);
        }
    }
}