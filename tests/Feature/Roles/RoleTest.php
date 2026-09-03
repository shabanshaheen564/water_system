<?php

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    // LIST
    public function test_authenticated_user_with_roles_view_can_list_roles(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'roles.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'guard_name',
                        'created_at',
                        'updated_at',
                        'permissions',
                    ],
                ],
            ]);

        $this->assertCount(6, $response->json('data'));
    }

    public function test_user_without_roles_view_cannot_list_roles(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/roles');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_receives_401_on_roles_list(): void
    {
        $response = $this->getJson('/api/roles');
        $response->assertStatus(401);
    }

    public function test_roles_list_includes_permissions(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/roles');

        $response->assertStatus(200);

        $roles = $response->json('data');
        $systemOwner = collect($roles)->firstWhere('name', 'System Owner');

        $this->assertNotNull($systemOwner);
        $this->assertIsArray($systemOwner['permissions']);
        $this->assertGreaterThan(0, count($systemOwner['permissions']));

        // Check that each permission has the expected structure
        foreach ($systemOwner['permissions'] as $perm) {
            $this->assertArrayHasKey('id', $perm);
            $this->assertArrayHasKey('name', $perm);
            $this->assertArrayHasKey('guard_name', $perm);
        }
    }

    public function test_roles_list_does_not_expose_sensitive_fields(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/roles');

        $response->assertStatus(200);

        $roles = $response->json('data');
        foreach ($roles as $role) {
            $this->assertArrayNotHasKey('password', $role);
            $this->assertArrayNotHasKey('token', $role);
            $this->assertArrayNotHasKey('remember_token', $role);
        }
    }

    // SHOW
    public function test_authenticated_user_with_roles_view_can_show_role(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.view')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::where('name', 'Admin')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'guard_name',
                'created_at',
                'updated_at',
                'permissions',
            ]);

        $this->assertEquals('Admin', $response->json('name'));
        $this->assertIsArray($response->json('permissions'));
    }

    public function test_user_without_roles_view_cannot_show_role(): void
    {
        $user = User::factory()->create();
        $role = \Spatie\Permission\Models\Role::where('name', 'Admin')->first();

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/roles/{$role->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_receives_401_on_role_show(): void
    {
        $role = \Spatie\Permission\Models\Role::where('name', 'Admin')->first();

        $response = $this->getJson("/api/roles/{$role->id}");
        $response->assertStatus(401);
    }

    public function test_nonexistent_role_returns_404(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.view')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/roles/999999');

        $response->assertStatus(404);
    }

    public function test_role_show_includes_permissions(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.view')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::where('name', 'Admin')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/roles/{$role->id}");

        $response->assertStatus(200);

        $permissions = $response->json('permissions');
        $this->assertIsArray($permissions);
        $this->assertGreaterThan(0, count($permissions));

        // Check that permissions have the expected structure
        foreach ($permissions as $perm) {
            $this->assertArrayHasKey('id', $perm);
            $this->assertArrayHasKey('name', $perm);
            $this->assertArrayHasKey('guard_name', $perm);
        }
    }

    // CREATE
    public function test_authenticated_user_with_roles_create_can_create_role(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/roles', [
            'name' => 'New Role',
            'permissions' => ['users.view', 'users.create'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'guard_name',
                'created_at',
                'updated_at',
                'permissions',
            ]);

        $this->assertEquals('New Role', $response->json('name'));
        $this->assertEquals('web', $response->json('guard_name'));
        $this->assertEquals(2, count($response->json('permissions')));

        $this->assertDatabaseHas('roles', ['name' => 'New Role']);

        $createdRole = \Spatie\Permission\Models\Role::where('name', 'New Role')->first();
        $this->assertTrue($createdRole->hasPermissionTo('users.view'));
        $this->assertTrue($createdRole->hasPermissionTo('users.create'));
    }

    public function test_user_without_roles_create_gets_403_on_create(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/roles', [
            'name' => 'New Role',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401_on_create(): void
    {
        $response = $this->postJson('/api/roles', [
            'name' => 'New Role',
        ]);

        $response->assertStatus(401);
    }

    public function test_duplicate_role_name_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        // Create a role first
        \Spatie\Permission\Models\Role::create(['name' => 'Existing Role', 'guard_name' => 'web']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/roles', [
            'name' => 'Existing Role',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_missing_name_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/roles', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_invalid_permission_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/roles', [
            'name' => 'New Role',
            'permissions' => ['non_existent_permission'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_valid_permissions_are_synchronized_on_create(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/roles', [
            'name' => 'New Role',
            'permissions' => ['users.view', 'users.create'],
        ]);

        $response->assertStatus(201);

        $createdRole = \Spatie\Permission\Models\Role::where('name', 'New Role')->first();
        $this->assertTrue($createdRole->hasPermissionTo('users.view'));
        $this->assertTrue($createdRole->hasPermissionTo('users.create'));
    }

    // UPDATE
    public function test_authenticated_user_with_roles_update_can_update_role(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Old Name', 'guard_name' => 'web']);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}", [
            'name' => 'Updated Role',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'guard_name',
                'created_at',
                'updated_at',
                'permissions',
            ]);

        $this->assertEquals('Updated Role', $response->json('name'));

        $role->refresh();
        $this->assertEquals('Updated Role', $role->name);
    }

    public function test_user_without_roles_update_gets_403_on_update(): void
    {
        $user = User::factory()->create();
        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}", [
            'name' => 'Updated Role',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401_on_update(): void
    {
        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

        $response = $this->putJson("/api/roles/{$role->id}", [
            'name' => 'Updated Role',
        ]);

        $response->assertStatus(401);
    }

    public function test_duplicate_name_on_update_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role1 = \Spatie\Permission\Models\Role::create(['name' => 'Role One', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'Role Two', 'guard_name' => 'web']);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role1->id}", [
            'name' => 'Role Two',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_invalid_permission_on_update_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}", [
            'name' => 'Updated Role',
            'permissions' => ['non_existent_permission'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_permissions_are_synchronized_on_update(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);
        $role->givePermissionTo('users.view');

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}", [
            'name' => 'Updated Role',
            'permissions' => ['users.view', 'users.create'],
        ]);

        $response->assertStatus(200);

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('users.view'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
        $this->assertFalse($role->hasPermissionTo('users.delete'));
    }

    // SYNC PERMISSIONS
    public function test_role_permissions_sync(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}/permissions", [
            'permissions' => ['users.view', 'users.create', 'users.update'],
        ]);

        $response->assertStatus(200);

        $this->assertEquals('Test Role', $response->json('name'));
        $this->assertCount(3, $response->json('permissions'));

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('users.view'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
        $this->assertTrue($role->hasPermissionTo('users.update'));
    }

    public function test_empty_permissions_array_removes_all_permissions(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);
        $role->givePermissionTo('users.view');

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}/permissions", [
            'permissions' => [],
        ]);

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('permissions'));

        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('users.view'));
    }

    public function test_sync_permissions_invalid_permission_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}/permissions", [
            'permissions' => 'not-an-array',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions']);
    $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions']);
    }

    // GUARD SAFETY
    public function test_guard_name_cannot_be_set_to_arbitrary_value_on_create(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.create')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        // Attempt to create role with arbitrary guard_name
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/roles', [
            'name' => 'Test Role',
            'guard_name' => 'api',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guard_name']);
    }

    public function test_guard_name_cannot_be_set_to_arbitrary_value_on_update(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}", [
            'name' => 'Updated Role',
            'guard_name' => 'api',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guard_name']);

        $role->refresh();
        $this->assertEquals('web', $role->guard_name);
    }

    // SYSTEM OWNER SAFETY
    public function test_system_owner_permissions_cannot_be_removed_via_sync(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $systemOwner = \Spatie\Permission\Models\Role::where('name', 'System Owner')->first();
        $originalPermissionCount = $systemOwner->permissions->count();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        // Attempt to remove all permissions from System Owner
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$systemOwner->id}/permissions", [
            'permissions' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'System Owner role must retain at least one permission.',
        ]);

        // Verify permissions were not removed
        $systemOwner->refresh();
        $this->assertEquals($originalPermissionCount, $systemOwner->permissions->count());
    }

    public function test_system_owner_permissions_cannot_be_removed_via_update(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $systemOwner = \Spatie\Permission\Models\Role::where('name', 'System Owner')->first();
        $originalPermissionCount = $systemOwner->permissions->count();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        // Attempt to remove all permissions from System Owner via update endpoint
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$systemOwner->id}", [
            'name' => 'System Owner',
            'permissions' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'System Owner role must retain at least one permission.',
        ]);

        // Verify permissions were not removed
        $systemOwner->refresh();
        $this->assertEquals($originalPermissionCount, $systemOwner->permissions->count());
    }

    public function test_empty_permissions_array_works_for_normal_roles(): void
    {
        $admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::where('name', 'roles.update')->first();
        $admin->givePermissionTo($permission);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Test Role', 'guard_name' => 'web']);
        $role->givePermissionTo('users.view');

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/roles/{$role->id}/permissions", [
            'permissions' => [],
        ]);

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('permissions'));

        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('users.view'));
    }
}