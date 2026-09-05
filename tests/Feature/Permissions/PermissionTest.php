<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    // LIST
    public function test_authenticated_user_with_permissions_view_can_list_permissions(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'permissions.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'guard_name',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $this->assertEquals(44, count($response->json('data')));
    }

    public function test_user_without_permissions_view_cannot_list_permissions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/permissions');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_receives_401_on_permissions_list(): void
    {
        $response = $this->getJson('/api/permissions');
        $response->assertStatus(401);
    }

    public function test_permissions_list_includes_expected_fields(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'permissions.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/permissions');

        $response->assertStatus(200);

        $permissions = $response->json('data');
        $this->assertEquals(44, count($permissions));

        foreach ($permissions as $perm) {
            $this->assertArrayHasKey('id', $perm);
            $this->assertArrayHasKey('name', $perm);
            $this->assertArrayHasKey('guard_name', $perm);
            $this->assertArrayHasKey('created_at', $perm);
            $this->assertArrayHasKey('updated_at', $perm);
        }
    }

    public function test_permissions_list_does_not_expose_sensitive_fields(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'permissions.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/permissions');

        $response->assertStatus(200);

        $permissions = $response->json('data');
        foreach ($permissions as $perm) {
            $this->assertArrayNotHasKey('password', $perm);
            $this->assertArrayNotHasKey('token', $perm);
            $this->assertArrayNotHasKey('remember_token', $perm);
        }
    }

    // SHOW
    public function test_authenticated_user_with_permissions_view_can_show_permission(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'permissions.view')->first();
        $admin->givePermissionTo($permission);

        $targetPermission = \Spatie\Permission\Models\Permission::where('name', 'users.view')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/permissions/{$targetPermission->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'guard_name',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals('users.view', $response->json('name'));
        $this->assertEquals('web', $response->json('guard_name'));
    }

    public function test_user_without_permissions_view_cannot_show_permission(): void
    {
        $user = User::factory()->create();
        $targetPermission = \Spatie\Permission\Models\Permission::where('name', 'users.view')->first();

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/permissions/{$targetPermission->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_receives_401_on_permission_show(): void
    {
        $targetPermission = \Spatie\Permission\Models\Permission::where('name', 'users.view')->first();

        $response = $this->getJson("/api/permissions/{$targetPermission->id}");
        $response->assertStatus(401);
    }

    public function test_nonexistent_permission_returns_404(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'permissions.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/permissions/999999');

        $response->assertStatus(404);
    }

    public function test_permissions_are_read_only(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'permissions.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        // Try to create a permission via POST (should not be allowed)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/permissions', [
            'name' => 'new.permission',
        ]);

        // Should fail - no create route for permissions
        $this->assertNotEquals(201, $response->getStatusCode());
    }
}