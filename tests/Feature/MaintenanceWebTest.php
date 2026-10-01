<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->givePermissionTo(Permission::whereIn('name', [
            'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.complete', 'maintenance.inspect',
        ])->get());
        $this->user->syncRoles([Role::findOrCreate('Field Worker', 'web')]);
    }

    public function test_guest_cannot_access_maintenance_web_page(): void
    {
        $this->get('/maintenance')->assertRedirect('/login');
    }

    public function test_user_without_maintenance_view_is_forbidden(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/maintenance')->assertForbidden();
    }

    public function test_maintenance_index_and_create_pages_render_for_authorized_user(): void
    {
        $dataset = $this->createDataset('wells_layer', 'Wells');
        $this->grantDataset($dataset);

        $this->actingAs($this->user)->get('/maintenance')
            ->assertOk()
            ->assertSee('طلبات الصيانة');

        $this->actingAs($this->user)->get('/maintenance/create')
            ->assertOk()
            ->assertSee('إنشاء طلب صيانة')
            ->assertSee('Wells');
    }

    public function test_settings_can_enable_or_disable_dataset_and_sync_roles(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->givePermissionTo(Permission::findByName('maintenance.update', 'web'));
        $dataset = $this->createDataset('tanks_layer', 'Tanks');

        $role = Role::findOrCreate('Engineer', 'web');

        $this->actingAs($admin)->post("/maintenance-settings/datasets/{$dataset->id}", [
            'maintenance_enabled' => false,
            'role_ids' => [$role->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('datasets', ['id' => $dataset->id, 'maintenance_enabled' => false]);
        $this->assertDatabaseHas('maintenance_dataset_role', ['dataset_id' => $dataset->id, 'role_id' => $role->id]);

        $this->assertFalse($dataset->fresh()->maintenance_enabled);
    }


    public function test_settings_can_update_maintenance_permissions_without_removing_other_role_permissions(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->givePermissionTo(Permission::findByName('maintenance.update', 'web'));
        $role = Role::findOrCreate('Engineer', 'web');
        $otherPermission = Permission::where('name', 'datasets.view')->firstOrFail();
        $role->givePermissionTo($otherPermission);

        $view = Permission::findByName('maintenance.view', 'web');

        $this->actingAs($admin)->post('/maintenance-settings/roles/permissions', [
            'role_permissions' => [
                $role->id => [$view->id],
            ],
        ])->assertRedirect();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('maintenance.view'));
        $this->assertFalse($role->hasPermissionTo('maintenance.create'));
        $this->assertTrue($role->hasPermissionTo('datasets.view'));
    }


    public function test_system_owner_can_access_enabled_maintenance_datasets_without_role_pivot(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->syncRoles([Role::findOrCreate('System Owner', 'web')]);
        $owner->givePermissionTo(Permission::whereIn('name', ['maintenance.view', 'maintenance.create'])->get());

        $dataset = $this->createDataset('owner_layer', 'Owner Layer');

        $this->actingAs($owner)
            ->get('/maintenance/create')
            ->assertOk()
            ->assertSee('Owner Layer');

        $this->actingAs($owner)
            ->getJson('/api/maintenance/datasets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $dataset->id);
    }

    public function test_disabled_dataset_is_not_available_even_when_role_is_granted(): void
    {
        $dataset = $this->createDataset('disabled_layer', 'Disabled Layer', false);
        $this->grantDataset($dataset);

        $this->actingAs($this->user)->getJson('/api/maintenance/datasets')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_web_can_create_update_and_execute_maintenance_request(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        $actor->syncRoles([Role::findOrCreate('Engineer', 'web')]);
        $actor->givePermissionTo(Permission::whereIn('name', [
            'maintenance.view', 'maintenance.create', 'maintenance.update',
            'maintenance.assign', 'maintenance.complete',
        ])->get());

        $dataset = $this->createDataset('wells_layer', 'Wells');
        DB::table('maintenance_dataset_role')->insert([
            'role_id' => $actor->roles()->first()->id,
            'dataset_id' => $dataset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $feature = $this->createFeature($dataset, 'W_02');

        $response = $this->actingAs($actor)->post('/maintenance', [
            'gis_feature_id' => $feature->id,
            'priority' => 'high',
            'assigned_to' => $actor->id,
            'problem_description' => 'المضخة لا تعمل.',
            'fault_description' => 'لا توجد استجابة عند التشغيل.',
        ]);

        $maintenance = MaintenanceRequest::query()->latest('id')->firstOrFail();

        $response->assertRedirect("/maintenance/{$maintenance->id}");
        $this->assertSame('new', $maintenance->status);

        $this->actingAs($actor)->put("/maintenance/{$maintenance->id}", [
            'priority' => 'urgent',
            'assigned_to' => $actor->id,
            'problem_description' => 'المضخة لا تعمل.',
            'fault_description' => 'تم تأكيد العطل.',
            'notes' => 'اختبار تحديث.',
        ])->assertRedirect("/maintenance/{$maintenance->id}");

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenance->id,
            'priority' => 'urgent',
            'status' => 'assigned',
            'assigned_to' => $actor->id,
        ]);

        $this->actingAs($actor)->post("/maintenance/{$maintenance->id}/jobs", [
            'technician_id' => $actor->id,
            'result' => 'repaired',
            'diagnosed_fault' => 'عطل في التشغيل.',
            'repair_action' => 'تمت إعادة التشغيل والإصلاح.',
            'materials_used' => 'لا يوجد.',
        ])->assertRedirect("/maintenance/{$maintenance->id}");

        $this->assertDatabaseHas('maintenance_jobs', [
            'maintenance_request_id' => $maintenance->id,
            'technician_id' => $actor->id,
            'result' => 'repaired',
        ]);
        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenance->id,
            'status' => 'completed',
        ]);
    }

    public function test_user_cannot_view_request_from_unallowed_dataset(): void
    {
        $dataset = $this->createDataset('restricted_layer', 'Restricted Layer');
        $feature = $this->createFeature($dataset, 'R-01');
        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'problem_description' => 'طلب مقيد.',
        ]);

        $this->actingAs($this->user)->get("/maintenance/{$maintenance->id}")->assertForbidden();
    }


    public function test_feature_endpoint_returns_gis_geometry_and_dynamic_values(): void
    {
        $dataset = $this->createDataset('wells_layer', 'Wells');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'W_02');

        $this->actingAs($this->user)
            ->getJson("/maintenance/datasets/{$dataset->id}/features")
            ->assertOk()
            ->assertJsonPath('data.0.id', $feature->id)
            ->assertJsonPath('data.0.identifier', 'W_02')
            ->assertJsonPath('data.0.geojson.type', 'Feature')
            ->assertJsonPath('data.0.geojson.geometry.type', 'Point')
            ->assertJsonPath('data.0.values.asset_code', 'W_02');
    }

    public function test_inspection_okay_is_saved_without_creating_maintenance_request(): void
    {
        $dataset = $this->createDataset('wells_layer', 'Wells');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'W_03');

        $this->actingAs($this->user)->post('/maintenance-inspections', [
            'gis_feature_id' => $feature->id,
            'result' => 'okay',
            'notes' => 'الفحص اليومي سليم.',
        ])->assertRedirect('/maintenance');

        $this->assertDatabaseHas('asset_inspections', [
            'gis_feature_id' => $feature->id,
            'inspected_by' => $this->user->id,
            'result' => 'okay',
        ]);
        $this->assertDatabaseMissing('maintenance_requests', ['gis_feature_id' => $feature->id]);
    }

    public function test_inspection_problem_creates_maintenance_request_for_same_gis_feature(): void
    {
        $dataset = $this->createDataset('wells_layer', 'Wells');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'W_04');

        $this->actingAs($this->user)->post('/maintenance-inspections', [
            'gis_feature_id' => $feature->id,
            'result' => 'problem',
            'problem_description' => 'تسرب ظاهر عند خط الطرد.',
            'notes' => 'يحتاج فني صيانة.',
        ])->assertRedirect();

        $maintenance = MaintenanceRequest::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('asset_inspections', [
            'gis_feature_id' => $feature->id,
            'result' => 'problem',
            'problem_description' => 'تسرب ظاهر عند خط الطرد.',
        ]);
        $this->assertSame($feature->id, $maintenance->gis_feature_id);
        $this->assertSame('new', $maintenance->status);
        $this->assertSame('تسرب ظاهر عند خط الطرد.', $maintenance->problem_description);
    }

    public function test_inspection_cannot_use_feature_from_unallowed_dataset(): void
    {
        $dataset = $this->createDataset('restricted_layer', 'Restricted Layer');
        $feature = $this->createFeature($dataset, 'R-02');

        $this->actingAs($this->user)->post('/maintenance-inspections', [
            'gis_feature_id' => $feature->id,
            'result' => 'problem',
            'problem_description' => 'مشكلة.',
        ])->assertForbidden();

        $this->assertDatabaseMissing('asset_inspections', ['gis_feature_id' => $feature->id]);
    }


    public function test_assignment_and_status_lifecycle_is_recorded(): void
    {
        $dataset = $this->createDataset('lifecycle_layer', 'Lifecycle Layer');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'L-01');

        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'problem_description' => 'Lifecycle test.',
        ]);

        $this->actingAs($this->user)->put("/maintenance/{$maintenance->id}", [
            'priority' => 'medium',
            'assigned_to' => $this->user->id,
            'status' => 'in_progress',
            'problem_description' => 'Lifecycle test.',
        ])->assertRedirect();

        $maintenance->refresh();
        $this->assertSame('in_progress', $maintenance->status);
        $this->assertNotNull($maintenance->assigned_at);
        $this->assertNotNull($maintenance->started_at);

        $this->actingAs($this->user)->put("/maintenance/{$maintenance->id}", [
            'priority' => 'medium',
            'assigned_to' => $this->user->id,
            'status' => 'waiting',
            'problem_description' => 'Lifecycle test.',
        ])->assertRedirect();

        $maintenance->refresh();
        $this->assertSame('waiting', $maintenance->status);
        $this->assertNotNull($maintenance->waiting_at);
    }

    public function test_multiple_execution_attempts_are_preserved_and_final_repair_completes_request(): void
    {
        $dataset = $this->createDataset('attempts_layer', 'Attempts Layer');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'A-01');

        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => 'assigned',
            'problem_description' => 'Multiple attempts.',
        ]);

        $this->actingAs($this->user)->post("/maintenance/{$maintenance->id}/jobs", [
            'result' => 'not_repaired',
            'diagnosed_fault' => 'المحاولة الأولى.',
        ])->assertRedirect();

        $this->actingAs($this->user)->post("/maintenance/{$maintenance->id}/jobs", [
            'result' => 'inspection_only',
            'diagnosed_fault' => 'فحص إضافي.',
        ])->assertRedirect();

        $this->actingAs($this->user)->post("/maintenance/{$maintenance->id}/jobs", [
            'result' => 'repaired',
            'repair_action' => 'تم الإصلاح في المحاولة الثالثة.',
        ])->assertRedirect();

        $this->assertSame(3, $maintenance->jobs()->count());
        $this->assertDatabaseHas('maintenance_jobs', ['maintenance_request_id' => $maintenance->id, 'result' => 'not_repaired']);
        $this->assertDatabaseHas('maintenance_jobs', ['maintenance_request_id' => $maintenance->id, 'result' => 'inspection_only']);
        $this->assertDatabaseHas('maintenance_jobs', ['maintenance_request_id' => $maintenance->id, 'result' => 'repaired']);
        $this->assertDatabaseHas('maintenance_requests', ['id' => $maintenance->id, 'status' => 'completed']);
    }

    public function test_cancellation_requires_reason_and_records_timestamp_for_manager(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $manager->syncRoles([Role::findOrCreate('Engineer', 'web')]);
        $manager->givePermissionTo(Permission::whereIn('name', [
            'maintenance.view', 'maintenance.update', 'maintenance.complete',
        ])->get());

        $dataset = $this->createDataset('cancel_layer', 'Cancel Layer');
        $feature = $this->createFeature($dataset, 'C-01');

        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'problem_description' => 'Cancel test.',
        ]);

        $this->actingAs($manager)->post("/maintenance/{$maintenance->id}/cancel", [
            'cancellation_reason' => 'تم إلغاء الطلب بعد المعالجة خارج النظام.',
        ])->assertRedirect();

        $maintenance->refresh();
        $this->assertSame('cancelled', $maintenance->status);
        $this->assertNotNull($maintenance->cancelled_at);
        $this->assertSame('تم إلغاء الطلب بعد المعالجة خارج النظام.', $maintenance->cancellation_reason);
    }

    public function test_field_worker_cannot_cancel_maintenance_request(): void
    {
        $dataset = $this->createDataset('cancel_security_layer', 'Cancel Security');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'CS-01');

        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'problem_description' => 'Cancel security.',
        ]);

        $this->actingAs($this->user)->post("/maintenance/{$maintenance->id}/cancel", [
            'cancellation_reason' => 'محاولة إلغاء.',
        ])->assertForbidden();

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenance->id,
            'status' => 'new',
        ]);
    }

    public function test_direct_terminal_status_change_is_rejected_and_execution_controls_completion(): void
    {
        $dataset = $this->createDataset('transition_layer', 'Transition Layer');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'T-01');

        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => 'assigned',
            'problem_description' => 'Transition security.',
        ]);

        $this->actingAs($this->user)->put("/maintenance/{$maintenance->id}", [
            'priority' => 'medium',
            'assigned_to' => $this->user->id,
            'status' => 'completed',
            'problem_description' => 'Transition security.',
        ])->assertSessionHasErrors('status');

        $maintenance->refresh();
        $this->assertSame('assigned', $maintenance->status);

        $this->actingAs($this->user)->post("/maintenance/{$maintenance->id}/jobs", [
            'result' => 'repaired',
            'repair_action' => 'تم الإصلاح فعلياً.',
        ])->assertRedirect();

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenance->id,
            'status' => 'completed',
        ]);
    }

    public function test_user_without_complete_permission_cannot_execute_maintenance(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::findOrCreate('Viewer', 'web')]);
        $user->givePermissionTo(Permission::whereIn('name', ['maintenance.view', 'maintenance.update'])->get());

        $dataset = $this->createDataset('authorization_layer', 'Authorization Layer');
        DB::table('maintenance_dataset_role')->insert([
            'role_id' => $user->roles()->first()->id,
            'dataset_id' => $dataset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $feature = $this->createFeature($dataset, 'AUTH-01');
        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $user->id,
            'problem_description' => 'Authorization test.',
        ]);

        $this->actingAs($user)->post("/maintenance/{$maintenance->id}/jobs", [
            'result' => 'repaired',
        ])->assertForbidden();

        $this->assertDatabaseCount('maintenance_jobs', 0);
    }

    public function test_field_worker_sees_only_requests_assigned_to_self(): void
    {
        $dataset = $this->createDataset('visibility_layer', 'Visibility Layer');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'V-01');

        $other = User::factory()->create(['is_active' => true]);
        $other->syncRoles([Role::findOrCreate('Field Worker', 'web')]);
        $otherRoleId = $other->roles()->first()->id;
        DB::table('maintenance_dataset_role')->insertOrIgnore([
            'role_id' => $otherRoleId,
            'dataset_id' => $dataset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mine = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'problem_description' => 'Mine.',
        ]);
        $otherRequest = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $other->id,
            'problem_description' => 'Other.',
        ]);
        $unassigned = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => null,
            'problem_description' => 'Unassigned.',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/maintenance/requests');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->actingAs($this->user)->getJson("/api/maintenance/requests/{$otherRequest->id}")
            ->assertForbidden();

        $this->actingAs($this->user)->getJson("/api/maintenance/requests/{$unassigned->id}")
            ->assertForbidden();
    }

    public function test_engineer_and_admin_can_see_unassigned_and_other_assigned_requests(): void
    {
        $dataset = $this->createDataset('management_visibility_layer', 'Management Visibility');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'MV-01');

        $other = User::factory()->create(['is_active' => true]);
        $other->syncRoles([Role::findOrCreate('Field Worker', 'web')]);

        $mine = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $other->id,
            'problem_description' => 'Assigned to another worker.',
        ]);
        $unassigned = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => null,
            'problem_description' => 'Unassigned request.',
        ]);

        foreach (['Engineer', 'Admin'] as $roleName) {
            $manager = User::factory()->create(['is_active' => true]);
            $manager->syncRoles([Role::findOrCreate($roleName, 'web')]);
            $manager->givePermissionTo(Permission::whereIn('name', [
                'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.assign',
                'maintenance.complete', 'maintenance.inspect',
            ])->get());
            DB::table('maintenance_dataset_role')->insert([
                'role_id' => $manager->roles()->first()->id,
                'dataset_id' => $dataset->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = $this->actingAs($manager)->getJson('/api/maintenance/requests');
            $response->assertOk();
            $ids = collect($response->json('data'))->pluck('id')->all();
            $this->assertContains($mine->id, $ids);
            $this->assertContains($unassigned->id, $ids);

            $this->actingAs($manager)->getJson("/api/maintenance/requests/{$unassigned->id}")
                ->assertOk();
        }
    }

    public function test_closed_request_cannot_be_reassigned(): void
    {
        $dataset = $this->createDataset('closed_assignment_layer', 'Closed Assignment');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'CA-01');

        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => 'assigned',
            'problem_description' => 'Closed assignment.',
        ]);

        $this->actingAs($this->user)->post("/maintenance/{$maintenance->id}/jobs", [
            'result' => 'repaired',
        ])->assertRedirect();

        $other = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->put("/maintenance/{$maintenance->id}", [
            'priority' => 'high',
            'assigned_to' => $other->id,
            'problem_description' => 'Closed assignment.',
        ])->assertStatus(422);
    }

    public function test_field_worker_cannot_assign_or_reassign_maintenance_request(): void
    {
        $dataset = $this->createDataset('assignment_security_layer', 'Assignment Security');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'AS-01');

        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'problem_description' => 'Assignment security.',
        ]);

        $other = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->put("/maintenance/{$maintenance->id}", [
            'priority' => 'high',
            'assigned_to' => $other->id,
            'problem_description' => 'Assignment security.',
        ])->assertForbidden();

        $maintenance->refresh();
        $this->assertSame($this->user->id, $maintenance->assigned_to);
    }

    private function createDataset(string $name, string $displayName, bool $enabled = true): Dataset
    {
        return Dataset::create([
            'name' => $name,
            'display_name' => $displayName,
            'dataset_type' => 'spatial_layer',
            'management_mode' => 'web_editable',
            'is_active' => true,
            'is_spatial' => true,
            'maintenance_enabled' => $enabled,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'map_order' => 0,
            'default_visible' => true,
            'map_opacity' => 1,
            'display_color' => '#475467',
            'created_by' => $this->user->id,
        ]);
    }

    private function grantDataset(Dataset $dataset): void
    {
        DB::table('maintenance_dataset_role')->insert([
            'role_id' => $this->user->roles()->first()->id,
            'dataset_id' => $dataset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createFeature(Dataset $dataset, string $identifier): GisFeature
    {
        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['asset_code' => $identifier],
            'identifier_value' => $identifier,
            'created_by' => $this->user->id,
        ]);

        DB::statement(
            "INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
             VALUES (?, ?, ST_SetSRID(ST_GeomFromText('POINT(34.75 31.42)'), 4326), ?, ?, ?, ?)",
            [$record->id, $dataset->id, 'Point', 4326, now(), now()]
        );

        return GisFeature::query()->where('dataset_record_id', $record->id)->firstOrFail();
    }
}
