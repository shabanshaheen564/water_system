<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Models\MaintenanceRequest;
use App\Models\Dataset;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    public function test_map_page_requires_a_relevant_map_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/map')->assertForbidden();
        $this->actingAs($user)->get('/map/data')->assertForbidden();
    }

    public function test_authorized_map_contains_satellite_layer_and_operational_data_endpoint(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('complaints.view', 'web'));

        $this->actingAs($user)
            ->get('/map')
            ->assertOk()
            ->assertSee('الخريطة التشغيلية')
            ->assertSee('data-satellite-layer-label="صورة جوية / ستالايت"', false)
            ->assertSee(route('map.data'), false);
    }

    public function test_complaint_viewer_receives_only_authorized_operational_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('complaints.view', 'web'));

        $complaint = Complaint::create([
            'complaint_number' => 'CMP-920001', 'title' => 'تسرب مياه', 'description' => 'بلاغ تجريبي',
            'status' => 'open', 'priority' => 'high', 'reported_by' => $user->id,
            'latitude' => 31.52, 'longitude' => 34.46,
        ]);

        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-920001', 'title' => 'إصلاح التسرب',
            'description' => 'مهمة تجريبية', 'status' => 'assigned', 'priority' => 'high', 'created_by' => $user->id,
        ]);
        $workOrder->complaints()->attach($complaint->id);

        $response = $this->actingAs($user)->get('/map/data')->assertOk();
        $response->assertJsonPath('permissions.complaints', true)->assertJsonPath('permissions.tasks', false)->assertJsonPath('permissions.datasets', false);
        $response->assertJsonPath('complaints.0.number', 'CMP-920001')->assertJsonCount(0, 'work_orders')->assertJsonCount(0, 'datasets');
        $this->assertNotNull($workOrder->id);
    }

    public function test_task_viewer_receives_direct_task_coordinates_without_needing_complaint_location(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('tasks.view', 'web'));

        WorkOrder::create([
            'work_order_number' => 'WO-920002', 'title' => 'إصلاح الخط', 'description' => 'مهمة تجريبية',
            'status' => 'in_progress', 'priority' => 'urgent', 'created_by' => $user->id,
            'latitude' => 31.50, 'longitude' => 34.48,
        ]);

        $this->actingAs($user)->get('/map/data')->assertOk()
            ->assertJsonPath('permissions.tasks', true)
            ->assertJsonPath('permissions.complaints', false)
            ->assertJsonPath('work_orders.0.number', 'WO-920002')
            ->assertJsonPath('work_orders.0.latitude', 31.5)
            ->assertJsonPath('work_orders.0.longitude', 34.48);
    }

    public function test_task_viewer_falls_back_to_linked_complaint_location(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('tasks.view', 'web'));
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-920003', 'title' => 'كسر خط', 'description' => 'بلاغ تجريبي',
            'status' => 'in_progress', 'priority' => 'urgent', 'reported_by' => $user->id,
            'latitude' => 31.50, 'longitude' => 34.48,
        ]);
        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-920003', 'title' => 'إصلاح الخط',
            'description' => 'مهمة تجريبية', 'status' => 'in_progress', 'priority' => 'urgent', 'created_by' => $user->id,
        ]);
        $workOrder->complaints()->attach($complaint->id);

        $this->actingAs($user)->get('/map/data')->assertOk()
            ->assertJsonPath('work_orders.0.latitude', 31.5)
            ->assertJsonPath('work_orders.0.longitude', 34.48);
    }

    public function test_maintenance_viewer_receives_georeferenced_maintenance_data(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([Role::findOrCreate('Engineer', 'web')]);
        $user->givePermissionTo(Permission::findByName('maintenance.view', 'web'));

        $dataset = Dataset::create([
            'name' => 'maintenance_map_test',
            'display_name' => 'طبقة صيانة اختبارية',
            'dataset_type' => 'spatial_layer',
            'management_mode' => 'web_editable',
            'is_active' => true,
            'is_spatial' => true,
            'maintenance_enabled' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $user->id,
        ]);
        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['name_ar' => 'بئر اختبار'],
            'identifier_value' => 'W-MAP-01',
            'created_by' => $user->id,
        ]);
        DB::statement(
            "INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
             VALUES (?, ?, ST_SetSRID(ST_GeomFromText('POINT(34.48 31.50)'), 4326), 'Point', 4326, ?, ?)",
            [$record->id, $dataset->id, now(), now()]
        );
        $feature = GisFeature::where('dataset_record_id', $record->id)->firstOrFail();

        $request = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $user->id,
            'priority' => 'high',
            'status' => 'assigned',
            'problem_description' => 'تسرب في الأصل',
        ]);

        $this->actingAs($user)->get('/map/data')->assertOk()
            ->assertJsonPath('permissions.maintenance', true)
            ->assertJsonPath('maintenance.0.id', $request->id)
            ->assertJsonPath('maintenance.0.number', $request->request_no)
            ->assertJsonPath('maintenance.0.asset_name', 'بئر اختبار')
            ->assertJsonPath('maintenance.0.latitude', 31.5)
            ->assertJsonPath('maintenance.0.longitude', 34.48);
    }

    public function test_dataset_viewer_receives_spatial_dataset_metadata(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('datasets.view', 'web'));
        Dataset::create([
            'name' => 'water_assets_test', 'display_name' => 'أصول المياه التجريبية', 'description' => 'طبقة اختبار',
            'dataset_type' => 'asset', 'is_active' => true, 'is_spatial' => true, 'geometry_type' => 'Point', 'srid' => 4326, 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get('/map/data')->assertOk()
            ->assertJsonPath('permissions.datasets', true)
            ->assertJsonPath('datasets.0.name', 'أصول المياه التجريبية');
    }
}
