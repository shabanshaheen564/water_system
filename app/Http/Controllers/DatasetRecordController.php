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

        // Dynamic filtering on JSONB values
        foreach ($request->all() as $key => $value) {
            if (in_array($key, ['page', 'per_page'])) {
                continue;
            }
            
            // Handle boolean values properly for JSONB filtering
            if (is_string($value) && in_array(strtolower($value), ['true', 'false'])) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            
            $query->whereJsonContains('values', [$key => $value]);
        }

        $records = $query->orderBy('created_at', 'desc')->paginate();

        $data = $records->getCollection()->map(function ($record) {
            return $this->formatRecord($record);
        });

        return response()->json([
            'data' => $data,
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
            $identifierField = $dataset->getIdentifierField();
            $identifierValue = null;

            if ($identifierField && isset($validated['values'][$identifierField->name])) {
                $identifierValue = $validated['values'][$identifierField->name];
            }

            $record = DatasetRecord::create([
                'dataset_id' => $dataset->id,
                'values' => $validated['values'],
                'identifier_value' => $identifierValue,
                'created_by' => $request->user()->id,
            ]);

            $record->load(['createdBy:id,name,email', 'updatedBy:id,name,email']);

            return response()->json($this->formatRecord($record), 201);
        });
    }

    public function show(Dataset $dataset, DatasetRecord $record): JsonResponse
    {
        $record->load(['createdBy:id,name,email', 'updatedBy:id,name,email']);

        return response()->json($this->formatRecord($record));
    }

    public function update(UpdateDatasetRecordRequest $request, Dataset $dataset, DatasetRecord $record): JsonResponse
    {
        $validated = $request->validated();

        // Remove protected fields
        unset(
            $validated['dataset_id'],
            $validated['created_by'],
            $validated['updated_by'],
            $validated['created_at'],
            $validated['updated_at'],
            $validated['identifier_value']
        );

        // Do NOT allow identifier_value to be changed through values
        $identifierField = $dataset->getIdentifierField();
        if ($identifierField && isset($validated['values'][$identifierField->name])) {
            // Remove identifier field from values to prevent changing it
            unset($validated['values'][$identifierField->name]);
        }

        $record->values = array_merge($record->values, $validated['values'] ?? []);
        $record->updated_by = $request->user()->id;
        $record->save();

        $record->load(['createdBy:id,name,email', 'updatedBy:id,name,email']);

        return response()->json($this->formatRecord($record));
    }

    private function formatRecord(DatasetRecord $record): array
    {
        return [
            'id' => $record->id,
            'dataset_id' => $record->dataset_id,
            'values' => $record->values,
            'identifier_value' => $record->identifier_value,
            'created_by' => $record->createdBy ? [
                'id' => $record->createdBy->id,
                'name' => $record->createdBy->name,
                'email' => $record->createdBy->email,
            ] : null,
            'updated_by' => $record->updatedBy ? [
                'id' => $record->updatedBy->id,
                'name' => $record->updatedBy->name,
                'email' => $record->updatedBy->email,
            ] : null,
            'created_at' => $record->created_at?->toISOString(),
            'updated_at' => $record->updated_at?->toISOString(),
        ];
    }
}