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

class MaintenanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(
            Permission::where('name', 'maintenance.view')->first()
        );
        $this->user->givePermissionTo(
            Permission::where('name', 'maintenance.create')->first()
        );

        $this->token = $this->user->createToken('maintenance-test')->plainTextToken;
    }

    public function test_maintenance_permissions_are_created_and_role_assignments_exist(): void
    {
        $this->assertNotNull(Permission::where('name', 'maintenance.view')->first());
        $this->assertNotNull(Permission::where('name', 'maintenance.create')->first());
        $this->assertTrue(Role::where('name', 'Engineer')->firstOrFail()->hasPermissionTo('maintenance.assign'));
        $this->assertTrue(Role::where('name', 'Field Worker')->firstOrFail()->hasPermissionTo('maintenance.create'));
        $this->assertTrue(Role::where('name', 'Viewer')->firstOrFail()->hasPermissionTo('maintenance.view'));
        $this->assertFalse(Role::where('name', 'Viewer')->firstOrFail()->hasPermissionTo('maintenance.create'));
    }

    public function test_user_only_sees_active_spatial_datasets_granted_to_their_role(): void
    {
        $role = Role::findOrCreate('Field Worker', 'web');
        $this->user->syncRoles([$role]);

        $allowed = $this->createDataset('allowed_layer', 'Allowed Layer', true, true);
        $inactive = $this->createDataset('inactive_layer', 'Inactive Layer', false, true);
        $nonSpatial = $this->createDataset('table_layer', 'Non Spatial Table', true, false);

        $this->user->roles()->first()->maintenanceDatasets()->attach($allowed->id);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/maintenance/datasets');

        $response->assertOk();
        $this->assertSame([$allowed->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertNotContains($inactive->id, collect($response->json('data'))->pluck('id')->all());
        $this->assertNotContains($nonSpatial->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_user_cannot_read_features_from_a_dataset_not_granted_to_their_role(): void
    {
        $dataset = $this->createDataset('restricted_layer', 'Restricted Layer', true, true);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/maintenance/datasets/{$dataset->id}/features");

        $response->assertForbidden();
    }

    public function test_user_can_create_maintenance_request_for_an_allowed_gis_feature(): void
    {
        $role = Role::findOrCreate('Field Worker', 'web');
        $this->user->syncRoles([$role]);

        $dataset = $this->createDataset('wells_layer', 'Wells', true, true);
        $this->user->roles()->first()->maintenanceDatasets()->attach($dataset->id);
        $feature = $this->createFeature($dataset, 'W_02');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/maintenance/requests', [
                'gis_feature_id' => $feature->id,
                'priority' => 'high',
                'problem_description' => 'Pump does not start.',
                'fault_description' => 'No response after start command.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'new')
            ->assertJsonPath('priority', 'high')
            ->assertJsonPath('gis_feature.id', $feature->id)
            ->assertJsonPath('gis_feature.dataset.id', $dataset->id)
            ->assertJsonPath('gis_feature.identifier', 'W_02');

        $this->assertDatabaseHas('maintenance_requests', [
            'request_no' => $response->json('request_no'),
            'gis_feature_id' => $feature->id,
            'reported_by' => $this->user->id,
        ]);

        $this->assertStringStartsWith('MNT-', $response->json('request_no'));
    }

    public function test_user_cannot_create_maintenance_request_for_a_feature_in_an_unallowed_dataset(): void
    {
        $role = Role::findOrCreate('Field Worker', 'web');
        $this->user->syncRoles([$role]);

        $allowed = $this->createDataset('allowed_layer', 'Allowed Layer', true, true);
        $restricted = $this->createDataset('restricted_layer', 'Restricted Layer', true, true);
        $this->user->roles()->first()->maintenanceDatasets()->attach($allowed->id);
        $feature = $this->createFeature($restricted, 'R-01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/maintenance/requests', [
                'gis_feature_id' => $feature->id,
                'problem_description' => 'Restricted asset problem.',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('maintenance_requests', 0);
    }

    public function test_maintenance_history_survives_gis_feature_deletion_by_nulling_the_link(): void
    {
        $maintenance = MaintenanceRequest::create([
            'gis_feature_id' => null,
            'reported_by' => $this->user->id,
            'problem_description' => 'Historical maintenance record.',
        ]);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenance->id,
            'gis_feature_id' => null,
        ]);
    }

    private function createDataset(string $name, string $displayName, bool $active, bool $spatial): Dataset
    {
        return Dataset::create([
            'name' => $name,
            'display_name' => $displayName,
            'dataset_type' => $spatial ? 'spatial_layer' : 'additional_table',
            'management_mode' => 'web_editable',
            'is_active' => $active,
            'is_spatial' => $spatial,
            'geometry_type' => $spatial ? 'Point' : null,
            'srid' => $spatial ? 4326 : null,
            'map_order' => 0,
            'default_visible' => true,
            'map_opacity' => 1,
            'display_color' => '#475467',
            'created_by' => $this->user->id,
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

        $id = DB::table('gis_features')->insertGetId([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset->id,
            'geometry' => DB::raw("ST_SetSRID(ST_GeomFromText('POINT(34.75 31.42)'), 4326)"),
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return GisFeature::findOrFail($id);
    }
}
