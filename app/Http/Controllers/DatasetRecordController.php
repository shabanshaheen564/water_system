<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetRecord\StoreDatasetRecordRequest;
use App\Http\Requests\DatasetRecord\UpdateDatasetRecordRequest;
use App\Models\Dataset;
use App\Models\DatasetRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        if ($identifierField && array_key_exists($identifierField->name, $values)) {
            unset($values[$identifierField->name]);
        }

        $record->values = array_merge($record->values ?? [], $values);
        $record->updated_by = $request->user()->id;
        $record->save();
        $record->load(['createdBy:id,name,email', 'updatedBy:id,name,email']);

        return response()->json($this->formatRecord($record));
    }

    public function destroy(Dataset $dataset, DatasetRecord $record): JsonResponse
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);

        if ($record->gisFeature()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a record while a GIS feature is linked to it. Delete the GIS feature first.',
            ], 409);
        }

        $record->delete();
        return response()->json(['message' => 'Record deleted successfully.']);
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
            'created_by' => $record->createdBy ? ['id' => $record->createdBy->id, 'name' => $record->createdBy->name, 'email' => $record->createdBy->email] : null,
            'updated_by' => $record->updatedBy ? ['id' => $record->updatedBy->id, 'name' => $record->updatedBy->name, 'email' => $record->updatedBy->email] : null,
            'created_at' => $record->created_at?->toISOString(),
            'updated_at' => $record->updated_at?->toISOString(),
        ];
    }
}
