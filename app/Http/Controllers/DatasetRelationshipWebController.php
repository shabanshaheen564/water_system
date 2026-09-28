<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRelationship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DatasetRelationshipWebController extends Controller
{
    public function create(Dataset $dataset): View
    {
        $dataset->load('fields');
        $datasets = Dataset::with('fields')
            ->where('id', '!=', $dataset->id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        $relationships = DatasetRelationship::where('parent_dataset_id', $dataset->id)
            ->with(['childDataset', 'parentField', 'childField'])
            ->latest()
            ->get();

        return view('datasets.relationships', compact('dataset', 'datasets', 'relationships'));
    }

    public function store(Request $request, Dataset $dataset): RedirectResponse
    {
        $validated = $request->validate([
            'child_dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'parent_field_id' => ['required', 'integer', 'exists:dataset_fields,id'],
            'child_field_id' => ['required', 'integer', 'exists:dataset_fields,id'],
        ]);

        $parentField = DatasetField::where('id', $validated['parent_field_id'])
            ->where('dataset_id', $dataset->id)
            ->first();

        $childDataset = Dataset::where('id', $validated['child_dataset_id'])
            ->where('is_active', true)
            ->first();

        $childField = $childDataset
            ? DatasetField::where('id', $validated['child_field_id'])->where('dataset_id', $childDataset->id)->first()
            : null;

        if (!$parentField || (!$parentField->is_identifier && !$parentField->is_unique)) {
            return back()->withErrors(['parent_field_id' => 'حقل البئر يجب أن يكون Identifier أو Unique.'])->withInput();
        }

        if (!$childDataset || !$childField) {
            return back()->withErrors(['child_field_id' => 'حقل الجدول التابع غير صحيح.'])->withInput();
        }

        if ($parentField->data_type !== $childField->data_type) {
            return back()->withErrors([
                'child_field_id' => "نوع الحقلين يجب أن يكون متطابقاً. البئر: {$parentField->data_type}، الجدول: {$childField->data_type}.",
            ])->withInput();
        }

        if (DatasetRelationship::where([
            'parent_dataset_id' => $dataset->id,
            'child_dataset_id' => $childDataset->id,
            'parent_field_id' => $parentField->id,
            'child_field_id' => $childField->id,
        ])->exists()) {
            return back()->withErrors(['child_dataset_id' => 'هذا الربط موجود مسبقاً.'])->withInput();
        }

        $parentValues = \App\Models\DatasetRecord::where('dataset_id', $dataset->id)
            ->pluck('values')
            ->map(fn ($values) => $values[$parentField->name] ?? null)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->flip();

        $unmatched = 0;
        $childDataset->records()->pluck('values')->each(function ($values) use (&$unmatched, $childField, $parentValues) {
            $value = $values[$childField->name] ?? null;
            if ($value !== null && $value !== '' && !$parentValues->has((string) $value)) {
                $unmatched++;
            }
        });

        if ($unmatched > 0) {
            return back()->withErrors([
                'child_field_id' => "لا يمكن إنشاء الربط حالياً: {$unmatched} قيمة في الجدول التابع غير موجودة في البيانات الأساسية للبئر.",
            ])->withInput();
        }

        DatasetRelationship::create([
            'parent_dataset_id' => $dataset->id,
            'child_dataset_id' => $childDataset->id,
            'parent_field_id' => $parentField->id,
            'child_field_id' => $childField->id,
            'relationship_type' => 'one_to_many',
            'on_delete_behavior' => 'restrict',
            'is_nullable' => true,
        ]);

        return back()->with('success', 'تم إنشاء العلاقة بين بيانات البئر والجدول التشغيلي.');
    }

    public function destroy(Dataset $dataset, DatasetRelationship $relationship): RedirectResponse
    {
        abort_unless($relationship->parent_dataset_id === $dataset->id, 404);
        $relationship->delete();

        return back()->with('success', 'تم حذف العلاقة فقط، ولم يتم حذف أي بيانات.');
    }
}
