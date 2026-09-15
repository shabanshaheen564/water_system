<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('datasets.view');
        $this->admin->givePermissionTo('datasets.create');
        $this->admin->givePermissionTo('datasets.update');

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('datasets.view');

        $this->viewer = User::factory()->create();
    }

    public function test_dashboard_accessible_to_authorized_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200)
            ->assertViewIs('dashboard.index')
            ->assertViewHas(['totalDatasets', 'totalRecords', 'spatialDatasets', 'gisFeatures', 'recentDatasets', 'systemStatus']);
    }

    public function test_dashboard_denied_to_unauthenticated_user(): void
    {
        $response = $this->get('/gis');
        $response->assertStatus(302);
    }

    public function test_dashboard_denied_to_user_without_permission(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get('/gis');
        $response->assertStatus(403);
    }

    public function test_dashboard_shows_real_statistics(): void
    {
        // Create test data
        $dataset1 = Dataset::create([
            'name' => 'dataset1',
            'display_name' => 'Dataset 1',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => false,
            'created_by' => $this->admin->id,
        ]);

        $dataset2 = Dataset::create([
            'name' => 'dataset2',
            'display_name' => 'Dataset 2',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $dataset1->id,
            'values' => ['name' => 'Record 1'],
            'identifier_value' => 'R-001',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $dataset1->id,
            'values' => ['name' => 'Record 2'],
            'identifier_value' => 'R-002',
            'created_by' => $this->admin->id,
        ]);

        DatasetRecord::create([
            'dataset_id' => $dataset2->id,
            'values' => ['name' => 'Record 3'],
            'identifier_value' => 'R-003',
            'created_by' => $this->admin->id,
        ]);

        // Add GIS feature
        $record = DatasetRecord::create([
            'dataset_id' => $dataset2->id,
            'values' => ['name' => 'Feature Record'],
            'identifier_value' => 'F-001',
            'created_by' => $this->admin->id,
        ]);

        GisFeature::create([
            'dataset_record_id' => $record->id,
            'dataset_id' => $dataset2->id,
            'geometry' => \Illuminate\Support\Facades\DB::selectOne(
                "SELECT ST_SetSRID(ST_GeomFromGeoJSON('{\"type\":\"Point\",\"coordinates\":[34.5,31.5]}'), 4326) as geometry"
            )->geometry,
            'geometry_type' => 'Point',
            'srid' => 4326,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('totalDatasets', 2);
        $response->assertViewHas('totalRecords', 4);
        $response->assertViewHas('spatialDatasets', 1);
        $response->assertViewHas('gisFeatures', 1);
    }

    public function test_dashboard_shows_recent_datasets(): void
    {
        $dataset = Dataset::create([
            'name' => 'recent_dataset',
            'display_name' => 'Recent Dataset',
            'dataset_type' => 'official_layer',
            'is_active' => true,
            'is_spatial' => false,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('recentDatasets', function ($datasets) use ($dataset) {
            return $datasets->contains('id', $dataset->id);
        });
    }

    public function test_dashboard_system_status(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/gis');

        $response->assertStatus(200);
        $response->assertViewHas('systemStatus', function ($status) {
            return isset($status['database']) && $status['database'] === 'online'
                && isset($status['api']) && $status['api'] === 'online'
                && isset($status['gis']);
        });
    }
}