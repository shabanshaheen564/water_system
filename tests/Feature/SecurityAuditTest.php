<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_is_rejected(): void
    {
        $this->getJson('/api/datasets')->assertUnauthorized();
    }

    public function test_viewer_cannot_create_dataset(): void
    {
        $permission = Permission::findOrCreate('datasets.view', 'web');
        $role = Role::findOrCreate('Viewer', 'web');
        $role->syncPermissions([$permission]);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user, 'sanctum')->postJson('/api/datasets', [
            'name' => 'forbidden',
            'display_name' => 'Forbidden',
            'dataset_type' => 'table',
        ])->assertForbidden();
    }

    public function test_dataset_changes_are_audited(): void
    {
        $permission = Permission::findOrCreate('datasets.create', 'web');
        $role = Role::findOrCreate('Audit Tester', 'web');
        $role->syncPermissions([$permission]);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user, 'sanctum');

        $dataset = Dataset::create([
            'name' => 'audit_layer',
            'display_name' => 'Audit Layer',
            'dataset_type' => 'table',
            'is_active' => true,
            'is_spatial' => false,
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Dataset::class,
            'auditable_id' => $dataset->id,
            'action' => 'created',
            'user_id' => $user->id,
        ]);
        $this->assertNotEmpty(AuditLog::where('auditable_id', $dataset->id)->first()->new_values);
    }
}
