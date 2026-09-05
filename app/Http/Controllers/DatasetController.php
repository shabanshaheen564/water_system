<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dataset\StoreDatasetRequest;
use App\Http\Requests\Dataset\UpdateDatasetRequest;
use App\Models\Dataset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatasetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Dataset::with(['createdBy:id,name,email', 'fields']);

        if ($request->has('dataset_type')) {
            $query->where('dataset_type', $request->dataset_type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $datasets = $query->orderBy('created_at', 'desc')->paginate();

        $data = $datasets->getCollection()->map(function ($dataset) {
            return $this->formatDataset($dataset);
        });

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $datasets->url(1),
                'last' => $datasets->url($datasets->lastPage()),
                'prev' => $datasets->previousPageUrl(),
                'next' => $datasets->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $datasets->currentPage(),
                'from' => $datasets->firstItem(),
                'last_page' => $datasets->lastPage(),
                'path' => $datasets->path(),
                'per_page' => $datasets->perPage(),
                'to' => $datasets->lastItem(),
                'total' => $datasets->total(),
            ],
        ]);
    }

    public function store(StoreDatasetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {
            $dataset = Dataset::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'dataset_type' => $validated['dataset_type'],
                'source_name' => $validated['source_name'] ?? null,
                'source_format' => $validated['source_format'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            $dataset->load(['createdBy:id,name,email', 'fields']);

            return response()->json($this->formatDataset($dataset), 201);
        });
    }

    public function show(Dataset $dataset): JsonResponse
    {
        $dataset->load(['createdBy:id,name,email', 'fields']);

        return response()->json($this->formatDataset($dataset));
    }

    public function update(UpdateDatasetRequest $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validated();

        // Remove protected fields
        unset(
            $validated['created_by'],
            $validated['created_at'],
            $validated['updated_at']
        );

        $dataset->update($validated);

        $dataset->load(['createdBy:id,name,email', 'fields']);

        return response()->json($this->formatDataset($dataset));
    }

    private function formatDataset(Dataset $dataset): array
    {
        return [
            'id' => $dataset->id,
            'name' => $dataset->name,
            'display_name' => $dataset->display_name,
            'description' => $dataset->description,
            'dataset_type' => $dataset->dataset_type,
            'source_name' => $dataset->source_name,
            'source_format' => $dataset->source_format,
            'is_active' => $dataset->is_active,
            'created_by' => $dataset->createdBy ? [
                'id' => $dataset->createdBy->id,
                'name' => $dataset->createdBy->name,
                'email' => $dataset->createdBy->email,
            ] : null,
            'fields' => $dataset->fields->map(function ($field) {
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'display_name' => $field->display_name,
                    'data_type' => $field->data_type,
                    'is_required' => $field->is_required,
                    'is_unique' => $field->is_unique,
                    'is_identifier' => $field->is_identifier,
                    'default_value' => $field->default_value,
                    'sort_order' => $field->sort_order,
                    'metadata' => $field->metadata,
                ];
            })->values(),
            'created_at' => $dataset->created_at?->toISOString(),
            'updated_at' => $dataset->updated_at?->toISOString(),
        ];
    }
}