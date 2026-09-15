<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetRecord\StoreDatasetRecordRequest;
use App\Http\Requests\DatasetRecord\UpdateDatasetRecordRequest;
use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\DatasetRelationship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatasetRecordController extends Controller
{
    public function index(Request $request, Dataset $dataset): JsonResponse
    {
        $query = DatasetRecord::where('dataset_id', $dataset->id)
            ->with(['createdBy:id,name,email', 'updatedBy:id,name,email']);

        foreach ($request->except(['page', 'per_page']) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $query->whereJsonContains('values', [$key => $value]);
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $records = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $records->getCollection()->map(fn (DatasetRecord $record) => $this->formatRecord($record))->values(),
            'links' => [
                'first' => $records->url(1),
                'last' => $records->url($records->lastPage()),
                'prev' => $records->previousPageUrl(),
                'next' => $records->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $records->currentPage(),
                'from' => $records->firstItem(),
                'last_page' => $records->lastPage(),
                'path' => $records->path(),
                'per_page' => $records->perPage(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function store(StoreDatasetRecordRequest $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $dataset, $request) {
            $values = $this->applyDefaults($dataset, $validated['values'] ?? []);
            $identifierField = $dataset->getIdentifierField();
            $identifierValue = $identifierField ? ($values[$identifierField->name] ?? null) : null;

            $this->validateChildReferences($dataset, $values);

            $record = DatasetRecord::create([
                'dataset_id' => $dataset->id,
                'values' => $values,
                'identifier_value' => $identifierValue !== null ? (string) $identifierValue : null,
                'created_by' => $request->user()->id,
            ]);

            $record->load(['createdBy:id,name,email', 'updatedBy:id,name,email']);
            return response()->json($this->formatRecord($record), 201);
        });
    }

    public function show(Dataset $dataset, DatasetRecord $record): JsonResponse
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);
        $record->load(['createdBy:id,name,email', 'updatedBy:id,name,email']);
        return response()->json($this->formatRecord($record));
    }

    public function update(UpdateDatasetRecordRequest $request, Dataset $dataset, DatasetRecord $record): JsonResponse
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);
        $validated = $request->validated();
        $values = $validated['values'] ?? [];
        $identifierField = $dataset->getIdentifierField();

        try {
            return DB::transaction(function () use ($dataset, $record, $identifierField, $values) {
                if ($identifierField && array_key_exists($identifierField->name, $values)) {
                    $newIdentifierValue = $values[$identifierField->name];
                    if ($newIdentifierValue !== $record->identifier_value) {
                        $this->preventIdentifierChangeIfChildrenExist($dataset, $record, $identifierField->name, $newIdentifierValue);
                    }
                    unset($values[$identifierField->name]);
                }

                $this->validateChildReferences($dataset, array_merge($record->values ?? [], $values), $record->id);

                $record->values = array_merge($record->values ?? [], $values);
                $record->updated_by = request()->user()->id;
                $record->save();
                $record->load(['createdBy:id,name,email', 'updatedBy:id,name,email']);

                return response()->json($this->formatRecord($record));
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function destroy(Dataset $dataset, DatasetRecord $record): JsonResponse
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);

        if ($record->gisFeature()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a record while a GIS feature is linked to it. Delete the GIS feature first.',
            ], 409);
        }

        try {
            return DB::transaction(function () use ($dataset, $record) {
                $this->handleParentDeletion($dataset, $record);

                $record->delete();
                return response()->json(['message' => 'Record deleted successfully.']);
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    private function validateChildReferences(Dataset $dataset, array $values, ?int $excludeRecordId = null): void
    {
        $relationships = DatasetRelationship::where('child_dataset_id', $dataset->id)
            ->with(['parentDataset', 'parentField', 'childField'])
            ->get();

        foreach ($relationships as $relationship) {
            $childField = $relationship->childField;
            $parentField = $relationship->parentField;
            $childValue = $values[$childField->name] ?? null;

            if ($childValue === null) {
                if (!$relationship->is_nullable && $childField->is_required) {
                    throw new \RuntimeException("Child field '{$childField->name}' is required and cannot be null for this relationship.");
                }
                continue;
            }

            $parentExists = DatasetRecord::where('dataset_id', $relationship->parent_dataset_id)
                ->whereJsonContains('values', [$parentField->name => $childValue])
                ->when($excludeRecordId, function ($query) use ($relationship, $excludeRecordId) {
                    $query->where('id', '!=', $excludeRecordId);
                })
                ->exists();

            if (!$parentExists) {
                throw new \RuntimeException("Referenced parent record not found for field '{$childField->name}' with value: {$childValue}.");
            }
        }
    }

    private function preventIdentifierChangeIfChildrenExist(Dataset $dataset, DatasetRecord $record, string $identifierFieldName, $newValue): void
    {
        $relationships = DatasetRelationship::where('parent_dataset_id', $dataset->id)
            ->where('parent_field_id', $dataset->getIdentifierField()?->id)
            ->with(['childDataset', 'childField'])
            ->get();

        foreach ($relationships as $relationship) {
            $childField = $relationship->childField;
            $oldValue = $record->identifier_value;

            if ($oldValue === null) {
                continue;
            }

            $hasChildren = DatasetRecord::where('dataset_id', $relationship->child_dataset_id)
                ->whereJsonContains('values', [$childField->name => $oldValue])
                ->exists();

            if ($hasChildren) {
                throw new \RuntimeException("Cannot change identifier field '{$identifierFieldName}' because existing child records depend on the current value.");
            }
        }
    }

    private function handleParentDeletion(Dataset $dataset, DatasetRecord $record): void
    {
        $relationships = $this->getParentRelationships($dataset);
        $parentIdentifierValue = $record->identifier_value;

        if ($parentIdentifierValue === null) {
            return;
        }

        $childRecords = $this->collectChildRecords($relationships, $parentIdentifierValue);

        if (empty($childRecords)) {
            return;
        }

        $this->validateDeletionConstraints($childRecords);
        $this->applyDeletionChanges($childRecords);
    }

    private function getParentRelationships(Dataset $dataset)
    {
        return DatasetRelationship::where('parent_dataset_id', $dataset->id)
            ->where('parent_field_id', $dataset->getIdentifierField()?->id)
            ->with(['childDataset', 'childField'])
            ->get();
    }

    private function collectChildRecords($relationships, string $parentIdentifierValue): array
    {
        $childRecords = [];
        foreach ($relationships as $relationship) {
            $childField = $relationship->childField;
            $children = DatasetRecord::where('dataset_id', $relationship->child_dataset_id)
                ->whereJsonContains('values', [$childField->name => $parentIdentifierValue])
                ->get();

            foreach ($children as $childRecord) {
                $childRecords[] = [
                    'record' => $childRecord,
                    'relationship' => $relationship,
                    'childField' => $childField,
                ];
            }
        }
        return $childRecords;
    }

    private function validateDeletionConstraints(array $childRecords): void
    {
        foreach ($childRecords as $item) {
            $relationship = $item['relationship'];
            $childRecord = $item['record'];
            $childField = $item['childField'];

            switch ($relationship->on_delete_behavior) {
                case 'restrict':
                    throw new RuntimeException("Cannot delete record: dependent child records exist in dataset '{$relationship->childDataset->name}' (field: {$childField->name}). Delete child records first or change delete behavior.");

                case 'cascade':
                    if ($childRecord->gisFeature()->exists()) {
                        throw new RuntimeException("Cannot cascade delete: child record (ID: {$childRecord->id}) has linked GIS feature.");
                    }
                    break;

                case 'set_null':
                    if (!$relationship->is_nullable) {
                        throw new RuntimeException("Cannot set null: relationship is not configured as nullable.");
                    }
                    break;
            }
        }
    }

    private function applyDeletionChanges(array $childRecords): void
    {
        foreach ($childRecords as $item) {
            $relationship = $item['relationship'];
            $childRecord = $item['record'];
            $childField = $item['childField'];

            switch ($relationship->on_delete_behavior) {
                case 'cascade':
                    $childRecord->delete();
                    break;

                case 'set_null':
                    $values = $childRecord->values ?? [];
                    $values[$childField->name] = null;
                    $childRecord->values = $values;
                    $childRecord->save();
                    break;
            }
        }
    }

    private function applyDefaults(Dataset $dataset, array $values): array
    {
        foreach ($dataset->fields as $field) {
            if (!array_key_exists($field->name, $values) && $field->default_value !== null) {
                $values[$field->name] = $field->default_value;
            }
        }
        return $values;
    }

    private function ensureRecordBelongsToDataset(Dataset $dataset, DatasetRecord $record): void
    {
        abort_unless($record->dataset_id === $dataset->id, 404);
    }

    private function formatRecord(DatasetRecord $record): array
    {
        return [
            'id' => $record->id,
            'dataset_id' => $record->dataset_id,
            'values' => $record->values,
            'identifier_value' => $record->identifier_value,
            'created_by' => $this->formatUser($record->createdBy),
            'updated_by' => $this->formatUser($record->updatedBy),
            'created_at' => $record->created_at?->toISOString(),
            'updated_at' => $record->updated_at?->toISOString(),
        ];
    }

    private function formatUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
