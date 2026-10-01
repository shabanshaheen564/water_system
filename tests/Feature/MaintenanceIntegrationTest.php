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

class MaintenanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    public function test_role_visibility_is_consistent_through_maintenance_api(): void
    {
        [$dataset, $feature] = $this->asset('visibility_integration', 'VI-01');

        $workerA = $this->user('Field Worker', [
            'maintenance.view', 'maintenance.update', 'maintenance.create',
            'maintenance.complete', 'maintenance.inspect',
        ]);
        $workerB = $this->user('Field Worker', [
            'maintenance.view', 'maintenance.update', 'maintenance.create',
            'maintenance.complete', 'maintenance.inspect',
        ]);
        $manager = $this->user('Engineer', [
            'maintenance.view', 'maintenance.update', 'maintenance.create',
            'maintenance.assign', 'maintenance.complete', 'maintenance.inspect',
        ]);

        $this->grant($dataset, [$workerA, $workerB]);

        $own = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $manager->id,
            'assigned_to' => $workerA->id,
            'problem_description' => 'Worker A request.',
        ]);
        $other = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $manager->id,
            'assigned_to' => $workerB->id,
            'problem_description' => 'Worker B request.',
        ]);
        $unassigned = MaintenanceRequest::create([
            'gis_feature_id' => $feature->id,
            'reported_by' => $manager->id,
            'problem_description' => 'Unassigned request.',
        ]);

        $this->actingAs($workerA)->getJson('/api/maintenance/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->actingAs($workerA)->getJson("/api/maintenance/requests/{$other->id}")
            ->assertForbidden();

        $this->actingAs($workerA)->getJson("/api/maintenance/requests/{$unassigned->id}")
            ->assertForbidden();

        $this->actingAs($manager)->getJson('/api/maintenance/requests')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($manager)->getJson("/api/maintenance/requests/{$unassigned->id}")
            ->assertOk()
            ->assertJsonPath('id', $unassigned->id);
    }

    public function test_complete_maintenance_cycle_preserves_gis_link_and_multiple_attempts(): void
    {
        [$dataset, $feature] = $this->asset('cycle_integration', 'CI-01');

        $workerA = $this->user('Field Worker', [
            'maintenance.view', 'maintenance.update', 'maintenance.create',
            'maintenance.complete',
        ]);
        $workerB = $this->user('Field Worker', [
            'maintenance.view', 'maintenance.update', 'maintenance.create',
            'maintenance.complete',
        ]);
        $manager = $this->user('Engineer', [
            'maintenance.view', 'maintenance.update', 'maintenance.create',
            'maintenance.assign', 'maintenance.complete',
        ]);
        $this->grant($dataset, [$workerA, $workerB]);

        $create = $this->actingAs($manager)->postJson('/api/maintenance/requests', [
            'gis_feature_id' => $feature->id,
            'priority' => 'high',
            'problem_description' => 'Full cycle test.',
        ])->assertCreated();

        $id = $create->json('id');
        $this->assertNotNull($id);

        $this->actingAs($manager)->putJson("/api/maintenance/requests/{$id}", [
            'priority' => 'high',
            'assigned_to' => $workerA->id,
            'problem_description' => 'Full cycle test.',
            'status' => 'assigned',
        ])->assertOk();

        $this->actingAs($workerA)->putJson("/api/maintenance/requests/{$id}", [
            'priority' => 'high',
            'assigned_to' => $workerA->id,
            'problem_description' => 'Full cycle test.',
            'status' => 'in_progress',
        ])->assertOk();

        $this->actingAs($workerA)->postJson("/api/maintenance/requests/{$id}/jobs", [
            'result' => 'not_repaired',
            'diagnosed_fault' => 'المحاولة الأولى.',
        ])->assertOk();

        $this->actingAs($manager)->putJson("/api/maintenance/requests/{$id}", [
            'priority' => 'high',
            'assigned_to' => $workerB->id,
            'problem_description' => 'Full cycle test.',
            'status' => 'in_progress',
        ])->assertOk();

        $this->actingAs($workerB)->postJson("/api/maintenance/requests/{$id}/jobs", [
            'result' => 'repaired',
            'diagnosed_fault' => 'العطل محدد.',
            'repair_action' => 'تم الإصلاح.',
            'materials_used' => 'لا يوجد.',
        ])->assertOk();

        $maintenance = MaintenanceRequest::with('jobs')->findOrFail($id);
        $this->assertSame('completed', $maintenance->status);
        $this->assertSame($workerB->id, $maintenance->assigned_to);
        $this->assertNotNull($maintenance->assigned_at);
        $this->assertNotNull($maintenance->started_at);
        $this->assertNotNull($maintenance->completed_at);
        $this->assertSame(2, $maintenance->jobs->count());
        $this->assertSame($feature->id, $maintenance->gis_feature_id);

        $show = $this->actingAs($manager)->getJson("/api/maintenance/requests/{$id}")
            ->assertOk();

        $show->assertJsonPath('gis_feature.id', $feature->id);
        $show->assertJsonPath('gis_feature.geometry.type', 'Point');
        $this->assertSame('CI-01', $show->json('gis_feature.identifier'));
        $this->assertCount(2, $show->json('jobs'));
    }

    public function test_problem_inspection_creates_request_on_same_gis_asset(): void
    {
        [$dataset, $feature] = $this->asset('inspection_integration', 'II-01');

        $worker = $this->user('Field Worker', [
            'maintenance.view', 'maintenance.update', 'maintenance.create',
            'maintenance.complete', 'maintenance.inspect',
        ]);
        $this->grant($dataset, [$worker]);

        $response = $this->actingAs($worker)->postJson('/api/maintenance-inspections', [
            'gis_feature_id' => $feature->id,
            'result' => 'problem',
            'problem_description' => 'مشكلة من الفحص اليومي.',
            'notes' => 'يحتاج فني.',
        ])->assertCreated();

        $maintenance = MaintenanceRequest::query()->latest('id')->firstOrFail();

        $response->assertJsonPath('id', $maintenance->id);
        $response->assertJsonPath('gis_feature.id', $feature->id);
        $response->assertJsonPath('status', 'new');
        $this->assertDatabaseHas('asset_inspections', [
            'gis_feature_id' => $feature->id,
            'result' => 'problem',
        ]);
        $this->assertSame($feature->id, $maintenance->gis_feature_id);
    }

    public function test_cancel_reopen_and_direct_completion_are_consistent(): void
    {
        [$dataset, $feature] = $this->asset('status_override_integration', 'SO-01');

        $manager = $this->user('Engineer', [
            'maintenance.view', 'maintenance.update', 'maintenance.create',
            'maintenance.assign', 'maintenance.complete', 'maintenance.inspect',
        ]);
        $this->grant($dataset, [$manager]);

        $created = $this->actingAs($manager)->postJson('/api/maintenance/requests', [
            'gis_feature_id' => $feature->id,
            'priority' => 'medium',
            'problem_description' => 'Status override test.',
        ])->assertCreated();

        $id = $created->json('id');

        $this->actingAs($manager)->putJson("/api/maintenance/requests/{$id}", [
            'priority' => 'medium',
            'problem_description' => 'Status override test.',
            'status' => 'completed',
        ])->assertOk();

        $this->actingAs($manager)->putJson("/api/maintenance/requests/{$id}", [
            'priority' => 'medium',
            'problem_description' => 'Status override test.',
            'status' => 'in_progress',
        ])->assertOk();

        $this->actingAs($manager)->putJson("/api/maintenance/requests/{$id}", [
            'priority' => 'medium',
            'problem_description' => 'Status override test.',
            'status' => 'cancelled',
            'cancellation_reason' => 'تم الإلغاء الإداري للاختبار.',
        ])->assertOk();

        $this->actingAs($manager)->putJson("/api/maintenance/requests/{$id}", [
            'priority' => 'medium',
            'problem_description' => 'Status override test.',
            'status' => 'in_progress',
        ])->assertOk();

        $maintenance = MaintenanceRequest::findOrFail($id);
        $this->assertSame('in_progress', $maintenance->status);
        $this->assertNotNull($maintenance->completed_at);
        $this->assertNotNull($maintenance->cancelled_at);
        $this->assertSame($feature->id, $maintenance->gis_feature_id);
    }

    private function user(string $role, array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::findOrCreate($role, 'web')]);
        $user->givePermissionTo(Permission::whereIn('name', $permissions)->get());
        return $user;
    }

    private function asset(string $name, string $identifier): array
    {
        $creator = User::query()->firstOrCreate(
            ['email' => 'maintenance-integration@tests.local'],
            ['name' => 'Maintenance Integration', 'username' => 'maintenance_integration', 'password' => bcrypt('password'), 'is_active' => true]
        );
        $dataset = Dataset::create([
            'name' => $name,
            'display_name' => ucwords(str_replace('_', ' ', $name)),
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
            'created_by' => $creator->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['asset_code' => $identifier, 'name_ar' => 'أصل اختبار'],
            'identifier_value' => $identifier,
            'created_by' => $creator->id,
        ]);

        DB::statement(
            "INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
             VALUES (?, ?, ST_SetSRID(ST_GeomFromText('POINT(34.75 31.42)'), 4326), 'Point', 4326, ?, ?)",
            [$record->id, $dataset->id, now(), now()]
        );

        return [$dataset, GisFeature::where('dataset_record_id', $record->id)->firstOrFail()];
    }

    private function grant(Dataset $dataset, array $users): void
    {
        foreach ($users as $user) {
            DB::table('maintenance_dataset_role')->insert([
                'role_id' => $user->roles()->first()->id,
                'dataset_id' => $dataset->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
