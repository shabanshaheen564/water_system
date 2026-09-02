<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CurrentUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_authenticated_user_can_get_current_data(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->assignRole($adminRole);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'last_login_at',
                    'roles' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                    'permissions' => [],
                ],
            ]);

        $this->assertEquals($user->id, $response->json('user.id'));
        $this->assertEquals($user->name, $response->json('user.name'));
        $this->assertEquals($user->email, $response->json('user.email'));
        $this->assertTrue($response->json('user.is_active'));
        $this->assertEquals('Admin', $response->json('user.roles.0.name'));
        $this->assertEquals($adminRole->id, $response->json('user.roles.0.id'));
        $this->assertIsArray($response->json('user.permissions'));
        $this->assertContains('users.view', $response->json('user.permissions'));
    }

    public function test_direct_permissions_are_included(): void
    {
        $user = User::factory()->create();
        $engineerRole = Role::where('name', 'Engineer')->first();
        $user->assignRole($engineerRole);

        $directPermission = Permission::where('name', 'users.create')->first();
        $user->givePermissionTo($directPermission);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);

        $permissions = $response->json('user.permissions');
        $this->assertContains('users.create', $permissions);

        // Should include role permissions too
        $this->assertContains('complaints.view', $permissions);
    }

    public function test_multiple_roles(): void
    {
        $user = User::factory()->create();
        $engineerRole = Role::where('name', 'Engineer')->first();
        $viewerRole = Role::where('name', 'Viewer')->first();
        $user->assignRole([$engineerRole, $viewerRole]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);

        $roles = $response->json('user.roles');
        $this->assertCount(2, $roles);

        $roleNames = array_column($roles, 'name');
        $this->assertContains('Engineer', $roleNames);
        $this->assertContains('Viewer', $roleNames);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_sensitive_fields_not_returned(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->assignRole($adminRole);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);

        $userData = $response->json('user');

        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);
        $this->assertArrayNotHasKey('personal_access_tokens', $userData);
        $this->assertArrayNotHasKey('token', $userData);
    }

    public function test_permissions_sorted_alphabetically(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->assignRole($adminRole);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);

        $permissions = $response->json('user.permissions');
        $sortedPermissions = $permissions;
        sort($sortedPermissions);

        $this->assertEquals($sortedPermissions, $permissions);
    }

    public function test_roles_sorted_consistently(): void
    {
        $user = User::factory()->create();
        $engineerRole = Role::where('name', 'Engineer')->first();
        $viewerRole = Role::where('name', 'Viewer')->first();

        // Assign in non-alphabetical order to test sorting
        $user->assignRole([$viewerRole, $engineerRole]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);

        $roles = $response->json('user.roles');
        $roleNames = array_column($roles, 'name');

        // Should be sorted alphabetically regardless of assignment order
        $sortedRoleNames = $roleNames;
        sort($sortedRoleNames);
        $this->assertEquals($sortedRoleNames, $roleNames);
    }
}