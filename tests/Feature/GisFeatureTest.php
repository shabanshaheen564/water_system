<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GisFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'web');
        $this->admin->assignRole($role);
        $this->adminToken = $this->admin->createToken('test-token')->plainTextToken;
    }

    // ... existing test methods ...

    public function test_feature_dataset_id_must_match_record_dataset(): void
    {
        // Create two datasets
        $dataset1 = Dataset::create([
            'name' => 'dataset_one',
            'display_name' => 'Dataset One',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $dataset2 = Dataset::create([
            'name' => 'dataset_two',
            'display_name' => 'Dataset Two',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset1->id,
            'values' => ['id' => '1'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        // Try to create feature with dataset2's ID but record from dataset1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset2->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [34.4668, 31.5326],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dataset_record_id']);
    }

    // Multi-geometry types test
    public function test_multipoint_feature(): void
    {
        $dataset = Dataset::create([
            'name' => 'multi_points',
            'display_name' => 'Multi Points',
            'dataset_type' => 'official_layer',
            'is_spatial' => true,
            'geometry_type' => 'MultiPoint',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        $record = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'values' => ['id' => '1'],
            'identifier_value' => '1',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$dataset->id}/features", [
            'dataset_record_id' => $record->id,
            'geometry' => [
                'type' => 'MultiPoint',
                'coordinates' => [[34.4668, 31.5326], [34.4669, 31.5327]],
            ],
        ]);

        $response->assertStatus(201);
    }
}
