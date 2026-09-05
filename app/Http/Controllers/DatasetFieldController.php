<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetField\StoreDatasetFieldRequest;
use App\Http\Requests\DatasetField\UpdateDatasetFieldRequest;
use App\Models\Dataset;
use App\Models\DatasetField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatasetFieldController extends Controller
{
    public function index(Request $request, Dataset $dataset): JsonResponse
    {
        $fields = $dataset->fields()->orderBy('sort_order')->paginate();

        $data = $fields->getCollection()->map(function ($field) {
            return $this->formatField($field);
        });

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $fields->url(1),
                'last' => $fields->url($fields->lastPage()),
                'prev' => $fields->previousPageUrl(),
                'next' => $fields->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $fields->currentPage(),
                'from' => $fields->firstItem(),
                'last_page' => $fields->lastPage(),
                'path' => $fields->path(),
                'per_page' => $fields->perPage(),
                'to' => $fields->lastItem(),
                'total' => $fields->total(),
            ],
        ]);
    }

    public function store(StoreDatasetFieldRequest $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $dataset) {
            $field = $dataset->fields()->create($validated);

            return response()->json($this->formatField($field), 201);
        });
    }

    public function update(UpdateDatasetFieldRequest $request, DatasetField $field): JsonResponse
    {
        $validated = $request->validated();

        // Remove protected fields
        unset(
            $validated['dataset_id'],
            $validated['created_at'],
            $validated['updated_at']
        );

        $field->update($validated);

        return response()->json($this->formatField($field));
    }

    private function formatField(DatasetField $field): array
    {
        return [
            'id' => $field->id,
            'dataset_id' => $field->dataset_id,
            'name' => $field->name,
            'display_name' => $field->display_name,
            'data_type' => $field->data_type,
            'is_required' => $field->is_required,
            'is_unique' => $field->is_unique,
            'is_identifier' => $field->is_identifier,
            'default_value' => $field->default_value,
            'sort_order' => $field->sort_order,
            'metadata' => $field->metadata,
            'created_at' => $field->created_at?->toISOString(),
            'updated_at' => $field->updated_at?->toISOString(),
        ];
    }
}