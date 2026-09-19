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

        if ($request->has('management_mode')) {
            $query->where('management_mode', $request->management_mode);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $datasets = $query->orderBy('created_at', 'desc')->paginate();

        $data = $datasets->getCollection()->map(fn ($dataset) => $this->formatDataset($dataset));

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
                'management_mode' => $validated['management_mode'],
                'source_name' => $validated['source_name'] ?? null,
                'source_format' => $validated['source_format'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_spatial' => $validated['is_spatial'] ?? false,
                'geometry_type' => $validated['geometry_type'] ?? null,
                'srid' => $validated['srid'] ?? null,
                'map_order' => $validated['map_order'] ?? 0,
                'default_visible' => $validated['default_visible'] ?? true,
                'map_opacity' => $validated['map_opacity'] ?? 1,
                'display_color' => $validated['display_color'] ?? '#475467',
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

        if ($this->changesProtectedConfiguration($dataset, $validated) && $this->hasDependentData($dataset)) {
            return response()->json([
                'message' => __('messages.controllers.dataset.protected_configuration'),
            ], 422);
        }

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
            'management_mode' => $dataset->management_mode,
            'source_name' => $dataset->source_name,
            'source_format' => $dataset->source_format,
            'is_active' => $dataset->is_active,
            'is_spatial' => $dataset->is_spatial,
            'geometry_type' => $dataset->geometry_type,
            'srid' => $dataset->srid,
            'map_order' => $dataset->map_order,
            'default_visible' => $dataset->default_visible,
            'map_opacity' => $dataset->map_opacity,
            'display_color' => $dataset->display_color,
            'created_by' => $dataset->createdBy ? [
                'id' => $dataset->createdBy->id,
                'name' => $dataset->createdBy->name,
                'email' => $dataset->createdBy->email,
            ] : null,
            'fields' => $dataset->fields->map(fn ($field) => [
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
            ])->values(),
            'created_at' => $dataset->created_at?->toISOString(),
            'updated_at' => $dataset->updated_at?->toISOString(),
        ];
    }

    private function changesProtectedConfiguration(Dataset $dataset, array $values): bool
    {
        foreach (['dataset_type', 'management_mode', 'is_spatial', 'geometry_type', 'srid'] as $attribute) {
            if (array_key_exists($attribute, $values) && $values[$attribute] != $dataset->{$attribute}) {
                return true;
            }
        }

        return false;
    }

    private function hasDependentData(Dataset $dataset): bool
    {
        return $dataset->records()->exists()
            || $dataset->gisFeatures()->exists()
            || $dataset->parentRelationships()->exists()
            || $dataset->childRelationships()->exists();
    }
}
