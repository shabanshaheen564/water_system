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

class MaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Dataset $dataset;
    protected GisFeature $feature;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->syncRoles([Role::findOrCreate('Field Worker', 'web')]);
        $this->user->givePermissionTo(Permission::whereIn('name', [
            'maintenance.view',
            'maintenance.create',
            'maintenance.update',
            'maintenance.complete',
            'maintenance.inspect',
        ])->get());

        $this->dataset = Dataset::create([
            'name' => 'api_wells_layer',
            'display_name' => 'API Wells',
            'dataset_type' => 'spatial_layer',
            'management_mode' => 'web_editable',
            'is_active' => true,
            'is_spatial' => true,
            'maintenance_enabled' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'map_order' => 0,
            'default_visible' => true,
            'map_opacity' => 1,
            'display_color' => '#475467',
            'created_by' => $this->user->id,
        ]);

        DB::table('maintenance_dataset_role')->insert([
            'role_id' => $this->user->roles()->first()->id,
            'dataset_id' => $this->dataset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['asset_code' => 'W_02', 'name_ar' => 'بئر الاختبار'],
            'identifier_value' => 'W_02',
            'created_by' => $this->user->id,
        ]);

        DB::statement(
            "INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
             VALUES (?, ?, ST_SetSRID(ST_GeomFromText('POINT(34.75 31.42)'), 4326), ?, ?, ?, ?)",
            [$record->id, $this->dataset->id, 'Point', 4326, now(), now()]
        );

        $this->feature = GisFeature::query()->where('dataset_record_id', $record->id)->firstOrFail();
    }

    public function test_mobile_api_can_list_datasets_and_features_with_geometry(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/maintenance/datasets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->dataset->id);

        $this->actingAs($this->user)
            ->getJson('/api/maintenance/datasets/' . $this->dataset->id . '/features')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->feature->id)
            ->assertJsonPath('data.0.values.name_ar', 'بئر الاختبار')
            ->assertJsonPath('data.0.geojson.geometry.type', 'Point');
    }

    public function test_mobile_api_can_create_update_and_execute_maintenance(): void
    {
        $create = $this->actingAs($this->user)->postJson('/api/maintenance/requests', [
            'gis_feature_id' => $this->feature->id,
            'priority' => 'high',
            'problem_description' => 'مشكلة من تطبيق الموبايل.',
        ]);

        $create->assertCreated()->assertJsonPath('status', 'new');
        $maintenanceId = $create->json('id');

        $this->actingAs($this->user)->putJson('/api/maintenance/requests/' . $maintenanceId, [
            'priority' => 'urgent',
            'status' => 'in_progress',
            'problem_description' => 'مشكلة من تطبيق الموبايل.',
        ])->assertOk()->assertJsonPath('status', 'in_progress');

        $this->actingAs($this->user)->postJson('/api/maintenance/requests/' . $maintenanceId . '/jobs', [
            'result' => 'repaired',
            'diagnosed_fault' => 'عطل تجريبي.',
            'repair_action' => 'تم الإصلاح.',
        ])->assertOk()->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('maintenance_jobs', [
            'maintenance_request_id' => $maintenanceId,
            'result' => 'repaired',
        ]);
    }

    public function test_mobile_api_inspection_problem_creates_request_and_cancel_requires_reason(): void
    {
        $inspection = $this->actingAs($this->user)->postJson('/api/maintenance-inspections', [
            'gis_feature_id' => $this->feature->id,
            'result' => 'problem',
            'problem_description' => 'مشكلة أثناء الفحص اليومي.',
        ]);

        $inspection->assertCreated()->assertJsonPath('status', 'new');
        $maintenanceId = $inspection->json('id');

        $this->actingAs($this->user)
            ->getJson('/api/maintenance/requests?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.id', $maintenanceId);

        $this->actingAs($this->user)->postJson('/api/maintenance/requests/' . $maintenanceId . '/cancel', [
            'cancellation_reason' => 'تم إلغاء الاختبار.',
        ])->assertOk()->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenanceId,
            'status' => 'cancelled',
            'cancellation_reason' => 'تم إلغاء الاختبار.',
        ]);
    }

    public function test_maintenance_create_replays_same_response_for_same_idempotency_key(): void
    {
        $payload = [
            'gis_feature_id' => $this->feature->id,
            'priority' => 'high',
            'problem_description' => 'Idempotency create test.',
            'idempotency_key' => 'mnt-create-idempotency-test',
        ];

        $first = $this->actingAs($this->user)
            ->postJson('/api/maintenance/requests', $payload)
            ->assertCreated();

        $second = $this->actingAs($this->user)
            ->postJson('/api/maintenance/requests', $payload)
            ->assertCreated();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('maintenance_requests', 1);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_maintenance_job_replays_without_creating_a_second_job(): void
    {
        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $this->feature->id,
            'reported_by' => $this->user->id,
            'problem_description' => 'Idempotency job test.',
            'status' => 'in_progress',
        ]);

        $payload = [
            'result' => 'repaired',
            'diagnosed_fault' => 'اختبار التكرار.',
            'repair_action' => 'تم الإصلاح.',
            'idempotency_key' => 'mnt-job-idempotency-test',
        ];

        $first = $this->actingAs($this->user)
            ->postJson('/api/maintenance/requests/' . $maintenance->id . '/jobs', $payload)
            ->assertOk();

        $second = $this->actingAs($this->user)
            ->postJson('/api/maintenance/requests/' . $maintenance->id . '/jobs', $payload)
            ->assertOk();

        $this->assertSame($first->json('status'), $second->json('status'));
        $this->assertSame('completed', $second->json('status'));
        $this->assertDatabaseCount('maintenance_jobs', 1);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_inspection_replays_without_creating_duplicate_inspection_or_request(): void
    {
        $payload = [
            'gis_feature_id' => $this->feature->id,
            'result' => 'problem',
            'problem_description' => 'Idempotency inspection test.',
            'idempotency_key' => 'mnt-inspection-idempotency-test',
        ];

        $first = $this->actingAs($this->user)
            ->postJson('/api/maintenance-inspections', $payload)
            ->assertCreated();

        $second = $this->actingAs($this->user)
            ->postJson('/api/maintenance-inspections', $payload)
            ->assertCreated();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('asset_inspections', 1);
        $this->assertDatabaseCount('maintenance_requests', 1);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_mobile_api_rejects_execution_without_complete_permission(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->syncRoles([Role::findOrCreate('Viewer', 'web')]);
        $viewer->givePermissionTo(Permission::whereIn('name', ['maintenance.view', 'maintenance.update'])->get());

        DB::table('maintenance_dataset_role')->insert([
            'role_id' => $viewer->roles()->first()->id,
            'dataset_id' => $this->dataset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => $this->feature->id,
            'reported_by' => $viewer->id,
            'problem_description' => 'Authorization test.',
        ]);

        $this->actingAs($viewer)->postJson('/api/maintenance/requests/' . $maintenance->id . '/jobs', [
            'result' => 'repaired',
        ])->assertForbidden();

        $this->assertDatabaseCount('maintenance_jobs', 0);
    }
}
