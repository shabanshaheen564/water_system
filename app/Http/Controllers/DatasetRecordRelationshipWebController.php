<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\DatasetRelationship;
use Illuminate\View\View;

class DatasetRecordRelationshipWebController extends Controller
{
    public function show(Dataset $dataset, DatasetRecord $record): View
    {
        abort_unless($record->dataset_id === $dataset->id, 404);

        $relationships = DatasetRelationship::where('parent_dataset_id', $dataset->id)
            ->with(['childDataset', 'parentField', 'childField'])
            ->get();

        $related = $relationships->map(function ($relationship) use ($record) {
            $value = $record->values[$relationship->parentField->name] ?? null;

            $children = $value === null || $value === ''
                ? collect()
                : DatasetRecord::where('dataset_id', $relationship->child_dataset_id)
                    ->whereJsonContains('values', [$relationship->childField->name => $value])
                    ->latest()
                    ->get();

            return [
                'relationship' => $relationship,
                'value' => $value,
                'fields' => $relationship->childDataset->fields()->orderBy('sort_order')->get(),
                'records' => $children,
            ];
        });

        return view('dataset_records.related', compact('dataset', 'record', 'related'));
    }
}
