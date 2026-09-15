<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetRelationship\StoreDatasetRelationshipRequest;
use App\Http\Requests\DatasetRelationship\UpdateDatasetRelationshipRequest;
use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatasetRelationshipController extends Controller
{
    public function index(Request $request, Dataset $dataset): JsonResponse
    {
        // Only return relationships where the route dataset is the parent.
        // This prevents IDOR where a user with access to dataset A can see
        // relationships where A is the child of dataset B without access to B.
        $query = DatasetRelationship::where('parent_dataset_id', $dataset->id)
            ->with([
                'parentDataset:id,name,display_name',
                'childDataset:id,name,display_name',
                'parentField:id,name,display_name',
                'childField:id,name,display_name',
            ]);

        $relationships = $query->orderBy('created_at', 'desc')->paginate();

        $data = $relationships->getCollection()->map(function ($rel) {
            return $this->formatRelationship($rel);
        });

        return response()->json([
            'data' => $data,
            'links' => [
                'first' => $relationships->url(1),
                'last' => $relationships->url($relationships->lastPage()),
                'prev' => $relationships->previousPageUrl(),
                'next' => $relationships->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $relationships->currentPage(),
                'from' => $relationships->firstItem(),
                'last_page' => $relationships->lastPage(),
                'path' => $relationships->path(),
                'per_page' => $relationships->perPage(),
                'to' => $relationships->lastItem(),
                'total' => $relationships->total(),
            ],
        ]);
    }

    public function show(Dataset $dataset, DatasetRelationship $relationship): JsonResponse
    {
        $this->ensureRelationshipBelongsToDataset($dataset, $relationship);

        $relationship->load([
            'parentDataset:id,name,display_name',
            'childDataset:id,name,display_name',
            'parentField:id,name,display_name',
            'childField:id,name,display_name',
        ]);

        return response()->json($this->formatRelationship($relationship));
    }

    public function store(StoreDatasetRelationshipRequest $request, Dataset $dataset): JsonResponse
    {
        $validated = $request->validated();

        $validated['parent_dataset_id'] = $dataset->id;
        $validated['on_delete_behavior'] = $validated['on_delete_behavior'] ?? 'restrict';
        $validated['is_nullable'] = $validated['is_nullable'] ?? false;

        return DB::transaction(function () use ($validated) {
            $relationship = DatasetRelationship::create($validated);

            $relationship->load([
                'parentDataset:id,name,display_name',
                'childDataset:id,name,display_name',
                'parentField:id,name,display_name',
                'childField:id,name,display_name',
            ]);

            return response()->json($this->formatRelationship($relationship), 201);
        });
    }

    public function update(UpdateDatasetRelationshipRequest $request, Dataset $dataset, DatasetRelationship $relationship): JsonResponse
    {
        $this->ensureRelationshipBelongsToDataset($dataset, $relationship);

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $relationship) {
            $relationship->update($validated);

            $relationship->load([
                'parentDataset:id,name,display_name',
                'childDataset:id,name,display_name',
                'parentField:id,name,display_name',
                'childField:id,name,display_name',
            ]);

            return response()->json($this->formatRelationship($relationship));
        });
    }

    public function destroy(Dataset $dataset, DatasetRelationship $relationship): JsonResponse
    {
        $this->ensureRelationshipBelongsToDataset($dataset, $relationship);

        $relationship->delete();

        return response()->json(['message' => 'Relationship deleted successfully.']);
    }

    private function ensureRelationshipBelongsToDataset(Dataset $dataset, DatasetRelationship $relationship): void
    {
        abort_unless(
            $relationship->parent_dataset_id === $dataset->id,
            404
        );
    }

    private function formatRelationship(DatasetRelationship $relationship): array
    {
        return [
            'id' => $relationship->id,
            'parent_dataset_id' => $relationship->parent_dataset_id,
            'child_dataset_id' => $relationship->child_dataset_id,
            'parent_field_id' => $relationship->parent_field_id,
            'child_field_id' => $relationship->child_field_id,
            'relationship_type' => $relationship->relationship_type,
            'on_delete_behavior' => $relationship->on_delete_behavior,
            'is_nullable' => $relationship->is_nullable,
            'parent_dataset' => $this->formatDataset($relationship->parentDataset),
            'child_dataset' => $this->formatDataset($relationship->childDataset),
            'parent_field' => $this->formatField($relationship->parentField),
            'child_field' => $this->formatField($relationship->childField),
            'created_at' => $relationship->created_at?->toISOString(),
            'updated_at' => $relationship->updated_at?->toISOString(),
        ];
    }

    private function formatDataset(?Dataset $dataset): ?array
    {
        if (!$dataset) {
            return null;
        }
        return [
            'id' => $dataset->id,
            'name' => $dataset->name,
            'display_name' => $dataset->display_name,
        ];
    }

    private function formatField(?DatasetField $field): ?array
    {
        if (!$field) {
            return null;
        }
        return [
            'id' => $field->id,
            'name' => $field->name,
            'display_name' => $field->display_name,
        ];
    }
}