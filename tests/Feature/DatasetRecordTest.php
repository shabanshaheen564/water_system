<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatasetRecordTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected Dataset $dataset;
    protected DatasetField $identifierField;
    protected DatasetField $stringField;
    protected DatasetField $integerField;
    protected DatasetField $decimalField;
    protected DatasetField $booleanField;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $permission = Permission::where('name', 'datasets.create')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'datasets.view')->first();
        $this->admin->givePermissionTo($permission);
        $permission = Permission::where('name', 'datasets.update')->first();
        $this->admin->givePermissionTo($permission);

        $this->adminToken = $this->admin->createToken('mobile-app')->plainTextToken;

        $this->dataset = Dataset::create([
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'created_by' => $this->admin->id,
        ]);

        $this->identifierField = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
        ]);

        $this->stringField = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'well_name',
            'display_name' => 'Well Name',
            'data_type' => 'string',
        ]);

        $this->integerField = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'depth',
            'display_name' => 'Depth',
            'data_type' => 'integer',
        ]);

        $this->decimalField = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'tds',
            'display_name' => 'TDS',
            'data_type' => 'decimal',
        ]);

        $this->booleanField = DatasetField::create([
            'dataset_id' => $this->dataset->id,
            'name' => 'is_active',
            'display_name' => 'Is Active',
            'data_type' => 'boolean',
        ]);
    }

    // Authentication & Authorization Tests
    public function test_unauthenticated_user_cannot_list_records(): void
    {
        $response = $this->getJson("/api/datasets/{$this->dataset->id}/records");
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_record(): void
    {
        $response = $this->postJson("/api/datasets/{$this->dataset->id}/records", [
            'values' => [
                'well_id' => 'W-001',
                'well_name' => 'Test Well',
            ],
        ]);
        $response->assertStatus(401);
    }

    public function test_user_without_create_permission_gets_403_on_create(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('name', 'datasets.view')->first();
        $user->givePermissionTo($permission);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/datasets/{$this->dataset->id}/records", [
            'values' => [
                'well_id' => 'W-001',
            ],
        ]);

        $response->assertStatus(403);
    }

    // CRUD Tests
    public function test_user_with_create_permission_can_create_record(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/records", [
            'values' => [
                'well_id' => 'W-001',
                'well_name' => 'Test Well',
                'depth' => 100,
                'tds' => 8500.5,
                'is_active' => true,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'dataset_id',
                'values',
                'identifier_value',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ]);

        $this->assertEquals('W-001', $response->json('values.well_id'));
        $this->assertEquals('Test Well', $response->json('values.well_name'));
        $this->assertEquals(100, $response->json('values.depth'));
        $this->assertEquals(8500.5, $response->json('values.tds'));
        $this->assertTrue($response->json('values.is_active'));
        $this->assertEquals('W-001', $response->json('identifier_value'));
        $this->assertEquals($this->admin->id, $response->json('created_by.id'));

        $this->assertDatabaseHas('dataset_records', [
            'dataset_id' => $this->dataset->id,
            'identifier_value' => 'W-001',
        ]);
    }

    public function test_create_record_missing_required_field_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/records", [
            'values' => [
                'well_name' => 'Test Well',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['values.well_id']);
    }

    public function test_create_record_with_duplicate_identifier_gets_422(): void
    {
        DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['well_id' => 'W-001', 'well_name' => 'Well 1'],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/records", [
            'values' => [
                'well_id' => 'W-001',
                'well_name' => 'Well 2',
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_record_with_unknown_field_gets_422(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/records", [
            'values' => [
                'well_id' => 'W-001',
                'unknown_field' => 'value',
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Unknown fields', $response->json('message'));
    }

    public function test_create_record_type_validation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/datasets/{$this->dataset->id}/records", [
            'values' => [
                'well_id' => 'W-001',
                'depth' => 'not_an_integer',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['values.depth']);
    }

    public function test_can_list_records_with_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $wellId = sprintf('W-%03d', $i);
            DatasetRecord::create([
                'dataset_id' => $this->dataset->id,
                'values' => ['well_id' => $wellId, 'well_name' => "Well {$i}"],
                'identifier_value' => $wellId,
                'created_by' => $this->admin->id,
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->dataset->id}/records");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_filter_records_by_field(): void
    {
        DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['well_id' => 'W-001', 'well_name' => 'Active Well', 'is_active' => true],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);
        DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['well_id' => 'W-002', 'well_name' => 'Inactive Well', 'is_active' => false],
            'identifier_value' => 'W-002',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->dataset->id}/records?is_active=true");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.values.is_active'));
    }

    public function test_can_show_record(): void
    {
        $record = DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['well_id' => 'W-001', 'well_name' => 'Test Well'],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->dataset->id}/records/{$record->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'dataset_id',
                'values',
                'identifier_value',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ]);
    }

    public function test_show_record_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/datasets/{$this->dataset->id}/records/999999");

        $response->assertStatus(404);
    }

    public function test_user_with_update_permission_can_update_record(): void
    {
        $record = DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['well_id' => 'W-001', 'well_name' => 'Original Name', 'depth' => 100],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$this->dataset->id}/records/{$record->id}", [
            'values' => [
                'well_name' => 'Updated Name',
                'depth' => 150,
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $response->json('values.well_name'));
        $this->assertEquals(150, $response->json('values.depth'));
        $this->assertEquals('W-001', $response->json('values.well_id')); // unchanged
        $this->assertEquals('W-001', $response->json('identifier_value')); // unchanged
    }

    public function test_cannot_update_identifier_value(): void
    {
        $record = DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['well_id' => 'W-001', 'well_name' => 'Test Well'],
            'identifier_value' => 'W-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/datasets/{$this->dataset->id}/records/{$record->id}", [
            'values' => [
                'well_id' => 'W-999',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('W-001', $response->json('identifier_value'));
    }

    public function test_update_record_with_duplicate_identifier_gets_422(): void
    {
        $record1 = DatasetRecord::create([
        'dataset_id' => $this->dataset->id,
        'values' => ['well_id' => 'W-001', 'well_name' => 'Well 1'],
        'identifier_value' => 'W-001',
        'created_by' => $this->admin->id,
    ]);

    $record2 = DatasetRecord::create([
        'dataset_id' => $this->dataset->id,
        'values' => ['well_id' => 'W-002', 'well_name' => 'Well 2'],
        'identifier_value' => 'W-002',
        'created_by' => $this->admin->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->adminToken,
    ])->putJson("/api/datasets/{$this->dataset->id}/records/{$record2->id}", [
        'values' => [
            'well_id' => 'W-001',
        ],
    ]);

    $response->assertStatus(422);
    }
}