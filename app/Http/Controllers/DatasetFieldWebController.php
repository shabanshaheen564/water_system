<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetRecord;
use App\Http\Requests\DatasetField\StoreDatasetFieldRequest;
use App\Http\Requests\DatasetField\UpdateDatasetFieldRequest;
use Illuminate\Http\Request;

class DatasetFieldWebController extends Controller
{
    public function index(Request $request, Dataset $dataset): \Illuminate\View\View
    {
        $fields = $dataset->fields()->orderBy('sort_order')->paginate(20);

        return view('dataset_fields.index', compact('dataset', 'fields'));
    }

    public function data(Dataset $dataset): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'data' => $dataset->fields()->orderBy('sort_order')->get()->map(function (DatasetField $field) {
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
                ];
            })->values(),
        ]);
    }

    public function create(Dataset $dataset): \Illuminate\View\View
    {
        return view('dataset_fields.create', compact('dataset'));
    }

    public function store(StoreDatasetFieldRequest $request, Dataset $dataset): \Illuminate\Http\RedirectResponse
    {
        $dataset->fields()->create($request->validated());

        return redirect()->route('datasets.fields.index', $dataset)
            ->with('success', 'Field created successfully.');
    }

    public function edit(Dataset $dataset, DatasetField $field): \Illuminate\View\View
    {
        $this->ensureFieldBelongsToDataset($dataset, $field);

        return view('dataset_fields.edit', ['fieldModel' => $field]);
    }

    public function update(UpdateDatasetFieldRequest $request, Dataset $dataset, DatasetField $field): \Illuminate\Http\RedirectResponse
    {
        $this->ensureFieldBelongsToDataset($dataset, $field);
        $validated = $request->validated();

        if ($this->changesStoredValueContract($field, $validated) && $this->fieldHasStoredValues($dataset, $field)) {
            return back()->withErrors([
                'field' => 'Field name and data type cannot be changed after records use this field.',
            ])->withInput();
        }

        $field->update($validated);

        return redirect()->route('datasets.fields.index', $dataset)
            ->with('success', 'Field updated successfully.');
    }

    public function destroy(Dataset $dataset, DatasetField $field): \Illuminate\Http\RedirectResponse
    {
        $this->ensureFieldBelongsToDataset($dataset, $field);

        if ($this->fieldHasStoredValues($dataset, $field)) {
            return back()->withErrors(['field' => 'Cannot delete field: existing records use this field.'])->withInput();
        }

        $field->delete();

        return redirect()->route('datasets.fields.index', $dataset)
            ->with('success', 'Field deleted successfully.');
    }

    private function ensureFieldBelongsToDataset(Dataset $dataset, DatasetField $field): void
    {
        abort_unless($field->dataset_id === $dataset->id, 404);
    }

    private function changesStoredValueContract(DatasetField $field, array $values): bool
    {
        return (array_key_exists('name', $values) && $values['name'] !== $field->name)
            || (array_key_exists('data_type', $values) && $values['data_type'] !== $field->data_type);
    }

    private function fieldHasStoredValues(Dataset $dataset, DatasetField $field): bool
    {
        return DatasetRecord::where('dataset_id', $dataset->id)
            ->whereRaw('jsonb_exists("values", ?)', [$field->name])
            ->exists();
    }
}
