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
            'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.complete',
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
        $dataset = $this->createDataset('wells_layer', 'Wells');
        $this->grantDataset($dataset);
        $feature = $this->createFeature($dataset, 'W_02');

        $response = $this->actingAs($this->user)->post('/maintenance', [
            'gis_feature_id' => $feature->id,
            'priority' => 'high',
            'assigned_to' => $this->user->id,
            'problem_description' => 'المضخة لا تعمل.',
            'fault_description' => 'لا توجد استجابة عند التشغيل.',
        ]);

        $maintenance = MaintenanceRequest::query()->latest('id')->firstOrFail();

        $response->assertRedirect("/maintenance/{$maintenance->id}");
        $this->assertSame('new', $maintenance->status);

        $this->actingAs($this->user)->put("/maintenance/{$maintenance->id}", [
            'priority' => 'urgent',
            'assigned_to' => $this->user->id,
            'problem_description' => 'المضخة لا تعمل.',
            'fault_description' => 'تم تأكيد العطل.',
            'notes' => 'اختبار تحديث.',
        ])->assertRedirect("/maintenance/{$maintenance->id}");

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenance->id,
            'priority' => 'urgent',
            'status' => 'assigned',
            'assigned_to' => $this->user->id,
        ]);

        $this->actingAs($this->user)->post("/maintenance/{$maintenance->id}/jobs", [
            'technician_id' => $this->user->id,
            'result' => 'repaired',
            'diagnosed_fault' => 'عطل في التشغيل.',
            'repair_action' => 'تمت إعادة التشغيل والإصلاح.',
            'materials_used' => 'لا يوجد.',
        ])->assertRedirect("/maintenance/{$maintenance->id}");

        $this->assertDatabaseHas('maintenance_jobs', [
            'maintenance_request_id' => $maintenance->id,
            'technician_id' => $this->user->id,
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
