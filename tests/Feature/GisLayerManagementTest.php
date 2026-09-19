<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GisLayerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        foreach (['datasets.create', 'datasets.view', 'datasets.update', 'gis.view'] as $permissionName) {
            $this->admin->givePermissionTo(Permission::where('name', $permissionName)->first());
        }

        $this->token = $this->admin->createToken('gis-layer-tests')->plainTextToken;
    }

    public function test_spatial_dataset_can_store_layer_display_settings(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/datasets', [
            'name' => 'water_pipes_layer',
            'display_name' => 'Water Pipes',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'LineString',
            'srid' => 28191,
            'map_order' => 20,
            'default_visible' => false,
            'map_opacity' => 0.65,
            'display_color' => '#0B74DE',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('map_order', 20)
            ->assertJsonPath('default_visible', false)
            ->assertJsonPath('map_opacity', 0.65)
            ->assertJsonPath('display_color', '#0B74DE');

        $this->assertDatabaseHas('datasets', [
            'name' => 'water_pipes_layer',
            'srid' => 28191,
            'map_order' => 20,
            'default_visible' => false,
            'display_color' => '#0B74DE',
        ]);
    }

    public function test_layer_display_settings_can_be_updated(): void
    {
        $dataset = Dataset::create([
            'name' => 'wells_layer',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/datasets/{$dataset->id}", [
            'map_order' => 5,
            'default_visible' => false,
            'map_opacity' => 0.8,
            'display_color' => '#16A34A',
        ]);

        $response->assertOk()
            ->assertJsonPath('map_order', 5)
            ->assertJsonPath('default_visible', false)
            ->assertJsonPath('map_opacity', 0.8)
            ->assertJsonPath('display_color', '#16A34A');
    }

    public function test_layer_settings_reject_invalid_opacity_and_color(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/datasets', [
            'name' => 'invalid_layer_style',
            'display_name' => 'Invalid Layer Style',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'map_opacity' => 1.5,
            'display_color' => 'blue',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['map_opacity', 'display_color']);
    }

    public function test_operational_map_returns_spatial_layers_in_map_order(): void
    {
        Dataset::create([
            'name' => 'layer_b',
            'display_name' => 'Layer B',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'map_order' => 20,
            'created_by' => $this->admin->id,
        ]);

        Dataset::create([
            'name' => 'layer_a',
            'display_name' => 'Layer A',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'map_order' => 10,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/map/operational');

        $response->assertOk();
        $datasets = $response->json('datasets');

        $this->assertSame(['Layer A', 'Layer B'], array_column($datasets, 'name'));
    }
}
