<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
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

    public function test_user_with_update_permission_can_open_operational_import_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('datasets.operational-import', $this->dataset));
        $response->assertOk()->assertSee('استيراد بيانات تشغيلية')->assertSee('ملف البيانات التشغيلية');
    }

    public function test_operational_import_updates_existing_record_without_creating_feature(): void
    {
        $beforeFeatureCount = DB::table('gis_features')->where('dataset_id', $this->dataset->id)->count();
        $preview = $this->actingAs($this->admin)->post(route('datasets.operational-import.preview', $this->dataset), [
            'file' => UploadedFile::fake()->createWithContent('operations.csv', "Asset_ID,status,daily_flow\nW_01,Under_Maintenance,25.75\n", 'text/csv'),
        ]);
        $preview->assertOk()->assertSee('ربط أعمدة البيانات التشغيلية');
        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $matches);
        $this->assertNotEmpty($matches[1]);

        $response = $this->actingAs($this->admin)->post(route('datasets.operational-import.confirm', $this->dataset), [
            'token' => $matches[1],
            'match_source_column' => 'Asset_ID',
            'match_target_field' => 'Asset_ID',
            'column_mapping' => ['Asset_ID' => 'Asset_ID', 'status' => 'status', 'daily_flow' => 'daily_flow'],
        ]);

        $response->assertRedirect(route('datasets.show', $this->dataset));
        $this->record->refresh();
        $this->assertSame('W_01', $this->record->values['Asset_ID']);
        $this->assertSame('Under_Maintenance', $this->record->values['status']);
        $this->assertEquals(25.75, (float) $this->record->values['daily_flow']);
        $this->assertSame($beforeFeatureCount, DB::table('gis_features')->where('dataset_id', $this->dataset->id)->count());
        $this->assertDatabaseHas('dataset_imports', ['dataset_id' => $this->dataset->id, 'status' => 'completed', 'successful_rows' => 1, 'failed_rows' => 0]);
    }

    public function test_operational_import_does_not_create_missing_layer_records(): void
    {
        $preview = $this->actingAs($this->admin)->post(route('datasets.operational-import.preview', $this->dataset), [
            'file' => UploadedFile::fake()->createWithContent('operations.csv', "Asset_ID,status,daily_flow\nW_99,Active,40\n", 'text/csv'),
        ]);
        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $matches);
        $response = $this->actingAs($this->admin)->post(route('datasets.operational-import.confirm', $this->dataset), [
            'token' => $matches[1],
            'match_source_column' => 'Asset_ID',
            'match_target_field' => 'Asset_ID',
            'column_mapping' => ['Asset_ID' => 'Asset_ID', 'status' => 'status', 'daily_flow' => 'daily_flow'],
        ]);

        $response->assertRedirect(route('datasets.show', $this->dataset));
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
