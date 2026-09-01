<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_authorized_user_can_register(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        $role = Role::where('name', 'Engineer')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $role->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'last_login_at',
                    'role' => [
                        'id',
                        'name',
                    ],
                ],
            ]);

        $this->assertEquals('User created successfully', $response->json('message'));
        $this->assertEquals('New User', $response->json('user.name'));
        $this->assertEquals('newuser@example.com', $response->json('user.email'));
        $this->assertTrue($response->json('user.is_active'));
        $this->assertNull($response->json('user.last_login_at'));
        $this->assertEquals('Engineer', $response->json('user.role.name'));

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'is_active' => true,
        ]);

        $createdUser = User::where('email', 'newuser@example.com')->first();
        $this->assertTrue(Hash::check('password123', $createdUser->password));
        $this->assertTrue($createdUser->hasRole('Engineer'));

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $createdUser->id,
        ]);
    }

    public function test_guest_cannot_register(): void
    {
        $role = Role::where('name', 'Engineer')->first();

        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $role->id,
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    public function test_authenticated_user_without_permission_cannot_register(): void
    {
        $role = Role::where('name', 'Engineer')->first();
        $user = User::factory()->create();

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $role->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    public function test_duplicate_email_fails(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        $role = Role::where('name', 'Engineer')->first();
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $role->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invalid_role_id_fails(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => 999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_password_confirmation_mismatch_fails(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        $role = Role::where('name', 'Engineer')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
            'role_id' => $role->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_required_fields_validation(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        $role = Role::where('name', 'Engineer')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role_id']);
    }

    public function test_system_owner_can_create_admin(): void
    {
        $systemOwner = User::factory()->create();
        $systemOwnerRole = Role::where('name', 'System Owner')->first();
        $systemOwner->assignRole($systemOwnerRole);

        $adminRole = Role::where('name', 'Admin')->first();

        $token = $systemOwner->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $adminRole->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Admin', $response->json('user.role.name'));
        $this->assertDatabaseHas('users', ['email' => 'newadmin@example.com']);
    }

    public function test_admin_cannot_create_system_owner(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        $systemOwnerRole = Role::where('name', 'System Owner')->first();

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', [
            'name' => 'New System Owner',
            'email' => 'newsowner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $systemOwnerRole->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Insufficient permissions to assign this role',
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'newsowner@example.com']);
    }

    public function test_admin_cannot_create_another_admin(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->assignRole($adminRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', [
            'name' => 'Another Admin',
            'email' => 'anotheradmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $adminRole->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Admin', $response->json('user.role.name'));
    }
}