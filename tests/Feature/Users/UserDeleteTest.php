<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    public function test_authorized_user_can_delete_non_system_owner(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::where('name', 'users.delete')->first());

        $targetUser = User::factory()->create();
        $targetId = $targetUser->id;
        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/users/{$targetId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $targetId]);
    }

    public function test_system_owner_cannot_be_deleted_even_by_system_owner(): void
    {
        $systemOwnerRole = Role::where('name', 'System Owner')->first();

        $actor = User::factory()->create();
        $actor->assignRole($systemOwnerRole);

        $targetUser = User::factory()->create();
        $targetUser->assignRole($systemOwnerRole);

        $token = $actor->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'System Owner users cannot be deleted.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $targetUser->id]);
        $this->assertTrue($targetUser->fresh()->hasRole('System Owner'));
    }

    public function test_system_owner_cannot_be_deleted_by_admin_with_delete_permission(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::where('name', 'users.delete')->first());

        $systemOwnerRole = Role::where('name', 'System Owner')->first();
        $targetUser = User::factory()->create();
        $targetUser->assignRole($systemOwnerRole);

        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'System Owner users cannot be deleted.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $targetUser->id]);
    }

    public function test_user_without_delete_permission_cannot_delete_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $targetUser->id]);
    }

    public function test_unauthenticated_user_cannot_delete_user(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(401);
        $this->assertDatabaseHas('users', ['id' => $targetUser->id]);
    }

    public function test_deleting_nonexistent_user_returns_404(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::where('name', 'users.delete')->first());
        $token = $admin->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/users/999999');

        $response->assertStatus(404);
    }
}
