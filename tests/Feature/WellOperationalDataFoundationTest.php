<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\DatasetRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WellOperationalDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_excel_table_can_be_imported_and_linked_to_wells(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo(Permission::where('name', 'datasets.create')->first());
        $user->givePermissionTo(Permission::where('name', 'datasets.update')->first());
        $user->givePermissionTo(Permission::where('name', 'datasets.view')->first());

        $wellDataset = Dataset::create([
            'name' => 'water_wells',
            'display_name' => 'Water Wells',
            'dataset_type' => 'official_layer',
            'management_mode' => 'official',
            'is_active' => true,
            'is_spatial' => true,
            'geometry_type' => 'Point',
            'srid' => 4326,
            'created_by' => $user->id,
        ]);

        $wellId = DatasetField::create([
            'dataset_id' => $wellDataset->id,
            'name' => 'Well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
            'is_required' => true,
            'is_unique' => true,
            'is_identifier' => true,
        ]);

        foreach (['W_01', 'W_02'] as $id) {
            DatasetRecord::create([
                'dataset_id' => $wellDataset->id,
                'values' => ['Well_id' => $id],
                'identifier_value' => $id,
                'created_by' => $user->id,
            ]);
        }

        $csv = UploadedFile::fake()->createWithContent(
            'tds_history.csv',
            "Well_id,TDS,Sample_Date\nW_01,2304,2026-09-01\nW_01,2410,2026-09-10\nW_02,3200,2026-09-05\n"
        );

        $this->actingAs($user)
            ->post('/datasets/operational-import/preview', ['file' => $csv])
            ->assertOk();

        $token = session('dataset_imports');
        $token = array_key_first($token);

        $this->actingAs($user)
            ->post('/datasets/operational-import/confirm', [
                'token' => $token,
                'name' => 'well_tds_history',
                'display_name' => 'Well TDS History',
                'description' => 'Historical TDS measurements',
                'field_types' => [
                    'Well_id' => 'string',
                    'TDS' => 'decimal',
                    'Sample_Date' => 'date',
                ],
                'parent_dataset_id' => $wellDataset->id,
                'parent_field_id' => $wellId->id,
                'child_field' => 'Well_id',
            ])
            ->assertRedirect();

        $child = Dataset::where('name', 'well_tds_history')->firstOrFail();

        $this->assertSame('additional_table', $child->dataset_type);
        $this->assertSame(3, $child->records()->count());

        $relationship = DatasetRelationship::where('parent_dataset_id', $wellDataset->id)
            ->where('child_dataset_id', $child->id)
            ->first();

        $this->assertNotNull($relationship);
        $this->assertSame($wellId->id, $relationship->parent_field_id);
        $this->assertSame('Well_id', $child->fields()->where('display_name', 'Well_id')->value('name'));

        $this->actingAs($user)
            ->get(route('datasets.relationships.index', $wellDataset))
            ->assertOk();

        $wellRecord = DatasetRecord::where('dataset_id', $wellDataset->id)
            ->where('identifier_value', 'W_01')
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('datasets.records.related', [$wellDataset, $wellRecord]))
            ->assertOk();
    }

    public function test_identifier_action_rejects_duplicate_values(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\RolesAndPermissionsSeeder']);

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo(Permission::where('name', 'datasets.update')->first());

        $dataset = Dataset::create([
            'name' => 'wells',
            'display_name' => 'Wells',
            'dataset_type' => 'official_layer',
            'created_by' => $user->id,
        ]);

        $field = DatasetField::create([
            'dataset_id' => $dataset->id,
            'name' => 'Well_id',
            'display_name' => 'Well ID',
            'data_type' => 'string',
        ]);

        foreach (['W_01', 'W_01'] as $id) {
            DatasetRecord::create([
                'dataset_id' => $dataset->id,
                'values' => ['Well_id' => $id],
            ]);
        }

        $this->actingAs($user)
            ->post(route('datasets.fields.identifier', [$dataset, $field]))
            ->assertSessionHasErrors('field');

        $this->assertFalse($field->fresh()->is_identifier);
    }
}
