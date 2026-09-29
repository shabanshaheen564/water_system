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

        Dataset::create([
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
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('datasets.index')
            ->with('success', 'Dataset created successfully.');
    }

    public function show(Dataset $dataset): \Illuminate\View\View
    {
        $dataset->load([
            'fields',
            'createdBy:id,name,email',
            'parentRelationships.childDataset',
            'childRelationships.parentDataset',
        ]);
        $recordsCount = $dataset->records()->count();
        $featuresCount = $dataset->gisFeatures()->count();

        return view('datasets.show', compact('dataset', 'recordsCount', 'featuresCount'));
    }

    public function unlinkOperationalRelationship(Dataset $dataset, \App\Models\DatasetRelationship $relationship): RedirectResponse
    {
        abort_unless(
            $relationship->parent_dataset_id === $dataset->id || $relationship->child_dataset_id === $dataset->id,
            404
        );

        $child = $relationship->childDataset;
        abort_unless(
            $child && $child->dataset_type === 'additional_table' && $child->management_mode === 'operational',
            404
        );

        \App\Models\DatasetRelationship::destroy($relationship->id);

        return back()->with('success', 'تم فك ارتباط الجدول التشغيلي. البيانات نفسها بقيت محفوظة ويمكن إعادة ربطها لاحقاً.');
    }

    public function destroy(Dataset $dataset): RedirectResponse
    {
        abort_unless(
            $dataset->dataset_type === 'additional_table'
                && $dataset->management_mode === 'operational',
            403
        );

        if ($dataset->gisFeatures()->exists()) {
            return back()->withErrors(['dataset' => 'لا يمكن حذف جدول تشغيلي يحتوي على معالم مكانية مرتبطة به.']);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($dataset) {
            \App\Models\DatasetRelationship::destroy(
                $dataset->parentRelationships()->pluck('id')->all()
            );
            \App\Models\DatasetRelationship::destroy(
                $dataset->childRelationships()->pluck('id')->all()
            );
            \App\Models\DatasetRecord::destroy(
                $dataset->records()->pluck('id')->all()
            );
            \App\Models\DatasetField::destroy(
                $dataset->fields()->pluck('id')->all()
            );
            \App\Models\DatasetImport::destroy(
                $dataset->imports()->pluck('id')->all()
            );
            \App\Models\Dataset::destroy($dataset->id);
        });

        return redirect()->route('datasets.index')->with('success', 'تم حذف جدول البيانات التشغيلية وجميع سجلاته.');
    }

    public function edit(Dataset $dataset): \Illuminate\View\View
    {
        return view('datasets.edit', compact('dataset'));
    }

    public function update(UpdateDatasetRequest $request, Dataset $dataset): RedirectResponse
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
