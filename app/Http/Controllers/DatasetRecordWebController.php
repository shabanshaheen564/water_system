<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetRecord\StoreDatasetRecordRequest;
use App\Http\Requests\DatasetRecord\UpdateDatasetRecordRequest;
use App\Models\Dataset;
use App\Models\DatasetRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DatasetRecordWebController extends Controller
{
    public function index(Request $request, Dataset $dataset): View
    {
        $search = trim((string) $request->input('search'));
        $records = $dataset->records()->with(['createdBy:id,name'])->latest();

        if ($search !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $records->where(function ($query) use ($escaped) {
                $query->where('identifier_value', 'ILIKE', "%{$escaped}%")
                    ->orWhereRaw('CAST(values AS TEXT) ILIKE ?', ["%{$escaped}%"]);
            });
        }

        $records = $records->paginate(20)->withQueryString();
        $dataset->load('fields');

        return view('dataset_records.index', compact('dataset', 'records', 'search'));
    }

    public function create(Dataset $dataset): View
    {
        $dataset->load('fields');
        return view('dataset_records.create', compact('dataset'));
    }

    public function store(StoreDatasetRecordRequest $request, Dataset $dataset): RedirectResponse
    {
        $values = $request->validated()['values'] ?? [];
        foreach ($dataset->fields as $field) {
            if (!array_key_exists($field->name, $values) && $field->default_value !== null) {
                $values[$field->name] = $field->default_value;
            }
        }

        $identifier = $dataset->getIdentifierField();
        $dataset->records()->create([
            'values' => $values,
            'identifier_value' => $identifier ? (string) ($values[$identifier->name] ?? '') : null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('datasets.records.index', $dataset)->with('success', 'Record created successfully.');
    }

    public function edit(Dataset $dataset, DatasetRecord $record): View
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);
        $dataset->load('fields');
        return view('dataset_records.edit', compact('dataset', 'record'));
    }

    public function update(UpdateDatasetRecordRequest $request, Dataset $dataset, DatasetRecord $record): RedirectResponse
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);
        $values = array_merge($record->values ?? [], $request->validated()['values'] ?? []);
        $identifier = $dataset->getIdentifierField();
        if ($identifier && array_key_exists($identifier->name, $values)) {
            $values[$identifier->name] = $record->values[$identifier->name] ?? $values[$identifier->name];
        }

        $record->update(['values' => $values, 'updated_by' => auth()->id()]);
        return redirect()->route('datasets.records.index', $dataset)->with('success', 'Record updated successfully.');
    }

    public function destroy(Dataset $dataset, DatasetRecord $record): RedirectResponse
    {
        $this->ensureRecordBelongsToDataset($dataset, $record);
        if ($record->gisFeature()->exists()) {
            return back()->withErrors(['record' => 'Cannot delete a record while a GIS feature is linked to it.']);
        }
        $record->delete();
        return back()->with('success', 'Record deleted successfully.');
    }

    private function ensureRecordBelongsToDataset(Dataset $dataset, DatasetRecord $record): void
    {
        abort_unless($record->dataset_id === $dataset->id, 404);
    }
}
