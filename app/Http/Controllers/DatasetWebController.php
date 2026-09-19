<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dataset\StoreDatasetRequest;
use App\Http\Requests\Dataset\UpdateDatasetRequest;
use App\Models\Dataset;
use Illuminate\Http\RedirectResponse;

class DatasetWebController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $datasets = Dataset::with(['fields', 'gisFeatures'])
            ->withCount(['records', 'gisFeatures as features_count', 'fields as fields_count'])
            ->orderBy('display_name')
            ->paginate(20);

        return view('datasets.index', compact('datasets'));
    }

    public function create(): \Illuminate\View\View
    {
        return view('datasets.create');
    }

    public function store(StoreDatasetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $dataset = Dataset::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'dataset_type' => $validated['dataset_type'],
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
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('datasets.index')
            ->with('success', 'Dataset created successfully.');
    }

    public function show(Dataset $dataset): \Illuminate\View\View
    {
        $dataset->load(['fields', 'createdBy:id,name,email']);
        $recordsCount = $dataset->records()->count();
        $featuresCount = $dataset->gisFeatures()->count();

        return view('datasets.show', compact('dataset', 'recordsCount', 'featuresCount'));
    }

    public function edit(Dataset $dataset): \Illuminate\View\View
    {
        return view('datasets.edit', compact('dataset'));
    }

    public function update(UpdateDatasetRequest $request, Dataset $dataset): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();

        if ($this->changesProtectedConfiguration($dataset, $validated) && $this->hasDependentData($dataset)) {
            return back()->withErrors([
                'dataset' => 'Spatial and type configuration cannot be changed after records, features, or relationships exist.',
            ])->withInput();
        }

        $dataset->update($validated);

        return redirect()->route('datasets.index')
            ->with('success', 'Dataset updated successfully.');
    }

    private function changesProtectedConfiguration(Dataset $dataset, array $values): bool
    {
        foreach (['dataset_type', 'is_spatial', 'geometry_type', 'srid'] as $attribute) {
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
