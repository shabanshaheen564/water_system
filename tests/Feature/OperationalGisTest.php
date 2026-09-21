<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperationalGisTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            Permission::where('name', 'complaints.view')->first(),
            Permission::where('name', 'complaints.update')->first(),
            Permission::where('name', 'tasks.view')->first(),
            Permission::where('name', 'tasks.update')->first(),
            Permission::where('name', 'gis.view')->first(),
        ]);
    }

    public function test_nearest_assets_returns_operational_spatial_features_in_distance_order(): void
    {
        [$dataset, $near, $far] = $this->makeAssets();

        $response = $this->actingAs($this->admin)->getJson('/api/gis/nearest-assets?latitude=31.5&longitude=34.45&limit=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $near->id)
            ->assertJsonPath('data.0.dataset_id', $dataset->id)
            ->assertJsonPath('data.1.id', $far->id);

        $this->assertLessThan(
            $response->json('data.1.distance_m'),
            $response->json('data.0.distance_m')
        );
    }

    public function test_complaint_can_link_and_unlink_gis_feature_and_context_returns_nearest(): void
    {
        [, $near] = $this->makeAssets();
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-GIS-000001',
            'title' => 'GIS test complaint',
            'description' => 'GIS operational context test',
            'status' => 'open',
            'priority' => 'high',
            'reported_by' => $this->admin->id,
            'latitude' => 31.5,
            'longitude' => 34.45,
        ]);

        $link = $this->actingAs($this->admin)
            ->postJson("/api/complaints/{$complaint->id}/gis/features/{$near->id}");

        $link->assertOk();

        $context = $this->actingAs($this->admin)
            ->getJson("/api/complaints/{$complaint->id}/gis/context");

        $context->assertOk()
            ->assertJsonPath('data.linked.0.id', $near->id)
            ->assertJsonPath('data.nearest.0.id', $near->id);

        $this->assertDatabaseHas('complaint_gis_feature', [
            'complaint_id' => $complaint->id,
            'gis_feature_id' => $near->id,
        ]);

        $unlink = $this->actingAs($this->admin)
            ->deleteJson("/api/complaints/{$complaint->id}/gis/features/{$near->id}");

        $unlink->assertOk();
        $this->assertDatabaseMissing('complaint_gis_feature', [
            'complaint_id' => $complaint->id,
            'gis_feature_id' => $near->id,
        ]);
    }

    public function test_work_order_can_link_gis_feature_and_web_page_shows_spatial_context(): void
    {
        [, $near] = $this->makeAssets();
        $workOrder = WorkOrder::create([
            'work_order_number' => 'WO-GIS-000001',
            'title' => 'GIS test work order',
            'description' => 'GIS operational context test',
            'status' => 'assigned',
            'priority' => 'medium',
            'assigned_to' => $this->admin->id,
            'created_by' => $this->admin->id,
            'latitude' => 31.5,
            'longitude' => 34.45,
        ]);

        $link = $this->actingAs($this->admin)
            ->postJson("/api/work-orders/{$workOrder->id}/gis/features/{$near->id}");

        $link->assertOk();

        $context = $this->actingAs($this->admin)
            ->getJson("/api/work-orders/{$workOrder->id}/gis/context");

        $context->assertOk()
            ->assertJsonPath('data.linked.0.id', $near->id)
            ->assertJsonPath('data.nearest.0.id', $near->id);

        $web = $this->actingAs($this->admin)
            ->get(route('work-orders.show', $workOrder));

        $web->assertOk()
            ->assertViewIs('work-orders.show')
            ->assertSee('السياق المكاني')
            ->assertSee('الأصول المرتبطة')
            ->assertSee('أقرب الأصول التشغيلية');
    }

    private function makeAssets(): array
    {
        $dataset = Dataset::create([
            'name' => 'operational_assets',
            'display_name' => 'Operational Assets',
            'dataset_type' => 'official_layer',
            'management_mode' => 'official',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $nearRecord = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['NAME' => 'Near Asset'],
            'identifier_value' => 'ASSET-NEAR',
            'created_by' => $this->admin->id,
        ]);

        $farRecord = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['NAME' => 'Far Asset'],
            'identifier_value' => 'ASSET-FAR',
            'created_by' => $this->admin->id,
        ]);

        $nearId = DB::table('gis_features')->insertGetId([
            'dataset_record_id' => $nearRecord->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::raw('ST_SetSRID(ST_GeomFromText('POINT(34.4501 31.5001)'), 4326)'),
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $farId = DB::table('gis_features')->insertGetId([
            'dataset_record_id' => $farRecord->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::raw('ST_SetSRID(ST_GeomFromText('POINT(34.50 31.55)'), 4326)'),
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$dataset, GisFeature::findOrFail($nearId), GisFeature::findOrFail($farId)];
    }
}
