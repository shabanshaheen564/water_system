<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Models\DatasetRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DatasetRecordRelationshipController extends Controller
{
    public function children(Request $request, Dataset $dataset, DatasetRecord $record): JsonResponse
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);

        $relationships = DatasetRelationship::where('parent_dataset_id', $dataset->id)
            ->where('parent_field_id', $dataset->getIdentifierField()?->id)
            ->with(['childDataset', 'childField'])
            ->get();

        $childRecords = [];
        foreach ($relationships as $relationship) {
            $this->ensureDatasetAccessible($relationship->childDataset);

            $childField = $relationship->childField;
            $parentIdentifierValue = $record->identifier_value;

            if ($parentIdentifierValue === null) {
                continue;
            }

            $children = DatasetRecord::where('dataset_id', $relationship->child_dataset_id)
                ->whereJsonContains('values', [$childField->name => $parentIdentifierValue])
                ->with(['createdBy:id,name,email', 'updatedBy:id,name,email'])
                ->get();

            foreach ($children as $child) {
                $childRecords[] = $this->formatRecordWithRelationship($child, $relationship, 'child');
            }
        }

        return response()->json([
            'data' => $childRecords,
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'path' => $request->url(),
                'per_page' => count($childRecords),
                'to' => count($childRecords),
                'total' => count($childRecords),
            ],
        ]);
    }

    public function parent(Request $request, Dataset $dataset, DatasetRecord $record): JsonResponse
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);

        $relationships = DatasetRelationship::where('child_dataset_id', $dataset->id)
            ->with(['parentDataset', 'parentField', 'childField'])
            ->get();

        foreach ($relationships as $relationship) {
            $this->ensureDatasetAccessible($relationship->parentDataset);

            $childField = $relationship->childField;
            $parentField = $relationship->parentField;
            $childValue = $record->values[$childField->name] ?? null;

            if ($childValue === null) {
                continue;
            }

            $parent = DatasetRecord::where('dataset_id', $relationship->parent_dataset_id)
                ->whereJsonContains('values', [$parentField->name => $childValue])
                ->with(['createdBy:id,name,email', 'updatedBy:id,name,email'])
                ->first();

            if ($parent) {
                return response()->json([
                    'data' => $this->formatRecordWithRelationship($parent, $relationship, 'parent'),
                    'links' => [
                        'first' => null,
                        'last' => null,
                        'prev' => null,
                        'next' => null,
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'from' => 1,
                        'last_page' => 1,
                        'path' => $request->url(),
                        'per_page' => 1,
                        'to' => 1,
                        'total' => 1,
                    ],
                ]);
            }
        }

        return response()->json([
            'data' => null,
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => null,
                'last_page' => 1,
                'path' => $request->url(),
                'per_page' => 0,
                'to' => null,
                'total' => 0,
            ],
        ]);
    }

    private function ensureRecordBelongsToDataset(Dataset $dataset, DatasetRecord $record): void
    {
        abort_unless($record->dataset_id === $dataset->id, 404);
    }

    private function ensureDatasetAccessible(?Dataset $dataset): void
    {
        // Verify the dataset exists and is active.
        // The user already has global datasets.view permission (enforced by route middleware).
        // If dataset-level permissions are added in the future, this check can be extended.
        abort_unless($dataset && $dataset->is_active, 404);
    }

    private function formatRecordWithRelationship(DatasetRecord $record, DatasetRelationship $relationship, string $direction): array
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
            'relationship' => [
                'id' => $relationship->id,
                'parent_dataset_id' => $relationship->parent_dataset_id,
                'child_dataset_id' => $relationship->child_dataset_id,
                'parent_field_id' => $relationship->parent_field_id,
                'child_field_id' => $relationship->child_field_id,
                'relationship_type' => $relationship->relationship_type,
                'on_delete_behavior' => $relationship->on_delete_behavior,
                'is_nullable' => $relationship->is_nullable,
                'direction' => $direction,
            ],
        ];
    }
}