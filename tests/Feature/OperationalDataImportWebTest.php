<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\DatasetRelationship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperationalDataImportWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Dataset $dataset;
    protected DatasetRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(Permission::where('name', 'datasets.view')->first());
        $this->admin->givePermissionTo(Permission::where('name', 'datasets.update')->first());

        $this->dataset = Dataset::create([
            'name' => 'test_operational_layer',
            'display_name' => 'Test Operational Layer',
            'dataset_type' => 'official_layer',
            'management_mode' => 'official',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $this->admin->id,
        ]);

        DatasetField::create(['dataset_id' => $this->dataset->id, 'name' => 'Asset_ID', 'display_name' => 'Asset ID', 'data_type' => 'string', 'is_unique' => true, 'is_identifier' => true, 'sort_order' => 1]);
        DatasetField::create(['dataset_id' => $this->dataset->id, 'name' => 'status', 'display_name' => 'Status', 'data_type' => 'string', 'sort_order' => 2]);
        DatasetField::create(['dataset_id' => $this->dataset->id, 'name' => 'daily_flow', 'display_name' => 'Daily Flow', 'data_type' => 'decimal', 'sort_order' => 3]);

        $this->record = DatasetRecord::create([
            'dataset_id' => $this->dataset->id,
            'values' => ['Asset_ID' => 'W_01', 'status' => 'Active', 'daily_flow' => 10.5],
            'identifier_value' => 'W_01',
            'created_by' => $this->admin->id,
        ]);

        DB::insert(
            'INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at) VALUES (?, ?, ST_SetSRID(ST_MakePoint(34.45, 31.50), 4326), ?, ?, NOW(), NOW())',
            [$this->record->id, $this->dataset->id, 'Point', 4326]
        );
    }

    private function import(string $csv, array $extra = []): \Illuminate\Testing\TestResponse
    {
        $preview = $this->actingAs($this->admin)->post(route('datasets.operational-import.preview', $this->dataset), [
            'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv, 'text/csv'),
        ]);
        $preview->assertOk();
        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $matches);
        $this->assertNotEmpty($matches[1]);

        return $this->actingAs($this->admin)->post(route('datasets.operational-import.confirm', $this->dataset), array_merge([
            'token' => $matches[1],
            'match_source_column' => 'Asset_ID',
            'import_columns' => [
                'Asset_ID' => '1',
                'status' => '1',
                'daily_flow' => '1',
            ],
        ], $extra));
    }

    public function test_user_with_update_permission_can_open_operational_import_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('datasets.operational-import', $this->dataset));
        $response->assertOk()->assertSee('استيراد بيانات تشغيلية')->assertSee('ملف البيانات التشغيلية');
    }

    public function test_operational_import_creates_supporting_table_and_relationship_without_creating_feature(): void
    {
        $beforeFeatureCount = DB::table('gis_features')->where('dataset_id', $this->dataset->id)->count();

        $response = $this->import("Asset_ID,status,daily_flow\nW_01,Under_Maintenance,25.75\n");
        $response->assertRedirect(route('datasets.show', $this->dataset));

        $this->record->refresh();
        $this->assertSame('Active', $this->record->values['status']);
        $this->assertEquals(10.5, (float) $this->record->values['daily_flow']);

        $supporting = Dataset::where('dataset_type', 'additional_table')
            ->where('name', 'test_operational_layer_operational_data')
            ->firstOrFail();

        $this->assertDatabaseHas('dataset_relationships', [
            'parent_dataset_id' => $this->dataset->id,
            'child_dataset_id' => $supporting->id,
            'parent_field_id' => $this->dataset->getIdentifierField()->id,
        ]);

        $child = DatasetRecord::where('dataset_id', $supporting->id)->firstOrFail();
        $this->assertSame('W_01', $child->values['asset_id']);
        $this->assertSame('Under_Maintenance', $child->values['status']);
        $this->assertEquals(25.75, (float) $child->values['daily_flow']);

        $this->assertSame($beforeFeatureCount, DB::table('gis_features')->where('dataset_id', $this->dataset->id)->count());
        $this->assertDatabaseHas('dataset_imports', ['dataset_id' => $this->dataset->id, 'status' => 'completed', 'successful_rows' => 1, 'failed_rows' => 0]);
    }

    public function test_operational_import_does_not_create_supporting_record_for_missing_parent(): void
    {
        $response = $this->import("Asset_ID,status,daily_flow\nW_99,Active,40\n");
        $response->assertRedirect(route('datasets.show', $this->dataset));

        $supporting = Dataset::where('name', 'test_operational_layer_operational_data')->firstOrFail();
        $this->assertSame(0, DatasetRecord::where('dataset_id', $supporting->id)->count());
        $this->assertSame(1, DatasetRecord::where('dataset_id', $this->dataset->id)->count());
        $this->assertDatabaseHas('dataset_imports', ['dataset_id' => $this->dataset->id, 'status' => 'failed', 'successful_rows' => 0, 'failed_rows' => 1]);
    }

    public function test_user_without_update_permission_cannot_import_operational_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::where('name', 'datasets.view')->first());
        $response = $this->actingAs($user)->get(route('datasets.operational-import', $this->dataset));
        $response->assertForbidden();
    }
}
