<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_user_with_users_update_can_update_user(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'is_active' => true,
        ]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_active' => false,
        ]);

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

        $this->assertEquals('Updated Name', $response->json('name'));
        $this->assertEquals('updated@example.com', $response->json('email'));
        $this->assertFalse($response->json('is_active'));

        $targetUser->refresh();
        $this->assertEquals('Updated Name', $targetUser->name);
        $this->assertEquals('updated@example.com', $targetUser->email);
        $this->assertFalse($targetUser->is_active);
    }

    public function test_name_email_is_active_updated(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'is_active' => true,
        ]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $response->json('name'));
        $this->assertEquals('new@example.com', $response->json('email'));
        $this->assertFalse($response->json('is_active'));

        $targetUser->refresh();
        $this->assertEquals('New Name', $targetUser->name);
        $this->assertEquals('new@example.com', $targetUser->email);
        $this->assertFalse($targetUser->is_active);
    }

    public function test_roles_sync_correctly(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $engineerRole = Role::where('name', 'Engineer')->first();
        $viewerRole = Role::where('name', 'Viewer')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($engineerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'is_active' => $targetUser->is_active,
            'roles' => ['Viewer'],
        ]);

        $response->assertStatus(200);

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('Viewer'));
        $this->assertFalse($targetUser->hasRole('Engineer'));
        $this->assertEquals('Viewer', $response->json('roles.0.name'));
    }

    public function test_duplicate_email_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => $targetUser->name,
            'email' => 'existing@example.com',
            'is_active' => $targetUser->is_active,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $targetUser->refresh();
        $this->assertNotEquals('existing@example.com', $targetUser->email);
    }

    public function test_invalid_data_gets_422(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        // Missing name
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'email' => 'test@example.com',
            'is_active' => true,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Invalid email
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => 'Test',
            'email' => 'invalid-email',
            'is_active' => true,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Invalid is_active
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => 'Test',
            'email' => 'test@example.com',
            'is_active' => 'invalid',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_user_without_users_update_gets_403(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'is_active' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->putJson("/api/users/{$targetUser->id}", [
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'is_active' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_nonexistent_user_returns_404(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/users/999999', [
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'is_active' => true,
        ]);

        $response->assertStatus(404);
    }

    public function test_password_cannot_be_changed_through_update_endpoint(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'password' => Hash::make('oldPassword123'),
        ]);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        // Try to change password via update endpoint
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'is_active' => $targetUser->is_active,
            'password' => 'newPassword123', // Should be ignored
        ]);

        $response->assertStatus(200);

        $targetUser->refresh();
        // Password should remain unchanged
        $this->assertTrue(Hash::check('oldPassword123', $targetUser->password));
    }

    public function test_sensitive_fields_not_returned(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $targetUser = User::factory()->create();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'is_active' => true,
        ]);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertArrayNotHasKey('personal_access_tokens', $data);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('plain_text_token', $data);
    }

    public function test_update_removes_roles_when_empty_array_sent(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::where('name', 'users.update')->first();
        $admin->givePermissionTo($permission);

        $engineerRole = Role::where('name', 'Engineer')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($engineerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/users/{$targetUser->id}", [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'is_active' => $targetUser->is_active,
            'roles' => [],
        ]);

        $response->assertStatus(200);

        $targetUser->refresh();
        $this->assertFalse($targetUser->hasRole('Engineer'));
        $this->assertEquals([], $response->json('roles'));
    }
}