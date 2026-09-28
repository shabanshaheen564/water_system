<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetImport;
use App\Models\DatasetRecord;
use App\Models\DatasetRelationship;
use App\Services\DatasetImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DatasetOperationalImportWebController extends Controller
{
    public function __construct(private readonly DatasetImportService $importService) {}

    public function create(): View
    {
        return view('datasets.operational-import');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:csv,xlsx'],
        ]);

        $file = $request->file('file');
        try {
            $preview = $this->importService->preview($file);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'تعذر قراءة الملف: '.$e->getMessage()])->withInput();
        }

        if ($preview['headers'] === []) {
            return back()->withErrors(['file' => 'الملف لا يحتوي على أعمدة.'])->withInput();
        }

        $token = (string) Str::uuid();
        $relativePath = 'dataset-imports/'.$token.'.'.$file->getClientOriginalExtension();
        $file->storeAs('dataset-imports', basename($relativePath), 'local');

        session()->put("dataset_imports.{$token}", [
            'path' => $relativePath,
            'original_filename' => $file->getClientOriginalName(),
            'source_format' => strtolower($file->getClientOriginalExtension()),
        ]);

        $datasets = Dataset::with('fields')
            ->where('is_active', true)
            ->where('dataset_type', '!=', 'additional_table')
            ->orderBy('display_name')
            ->get();

        return view('datasets.operational-import-preview', compact('preview', 'token', 'datasets', 'state'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:datasets,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'field_types' => ['required', 'array', 'min:1'],
            'field_types.*' => ['required', 'in:string,text,integer,decimal,boolean,date,datetime'],
            'parent_dataset_id' => ['nullable', 'integer', 'exists:datasets,id'],
            'parent_field_id' => ['nullable', 'integer', 'exists:dataset_fields,id'],
            'child_field' => ['nullable', 'string', 'max:255'],
        ]);

        $state = session()->get("dataset_imports.{$validated['token']}");
        abort_unless($state, 404);

        $path = Storage::disk('local')->path($state['path']);
        if (!is_file($path)) {
            return back()->withErrors(['token' => 'ملف الاستيراد المؤقت غير موجود.'])->withInput();
        }

        $preview = $this->importService->preview(
            new \Illuminate\Http\UploadedFile(
                $path,
                $state['original_filename'],
                null,
                null,
                true
            )
        );

        $headers = $preview['headers'];
        $fieldTypes = $validated['field_types'];
        $fieldMap = $this->buildFieldMap($headers);

        foreach ($headers as $header) {
            if (!isset($fieldTypes[$header])) {
                return back()->withErrors(['field_types' => "لم يتم تحديد نوع العمود: {$header}"])->withInput();
            }
        }

        if (($validated['parent_dataset_id'] ?? null) !== null) {
            if (!$validated['parent_field_id'] || !$validated['child_field']) {
                return back()->withErrors(['parent_dataset_id' => 'عند ربط الجدول بالبئر يجب تحديد حقل البئر وحقل الربط في الملف.'])->withInput();
            }

            $parentDataset = Dataset::findOrFail($validated['parent_dataset_id']);
            $parentField = DatasetField::where('id', $validated['parent_field_id'])
                ->where('dataset_id', $parentDataset->id)
                ->first();

            if (!$parentField || (!$parentField->is_identifier && !$parentField->is_unique)) {
                return back()->withErrors(['parent_field_id' => 'حقل الربط في الطبقة الأساسية يجب أن يكون Identifier أو Unique.'])->withInput();
            }

            if (!in_array($validated['child_field'], $headers, true)) {
                return back()->withErrors(['child_field' => 'حقل الربط المختار غير موجود في الملف.'])->withInput();
            }

            if ($fieldTypes[$validated['child_field']] !== $parentField->data_type) {
                return back()->withErrors([
                    'child_field' => "نوع حقل الربط لا يطابق نوع حقل البئر ({$parentField->data_type}).",
                ])->withInput();
            }
        }

        $dataset = null;
        try {
            DB::transaction(function () use (&$dataset, $validated, $headers, $fieldTypes, $state, $path) {
                $dataset = Dataset::create([
                    'name' => $validated['name'],
                    'display_name' => $validated['display_name'],
                    'description' => $validated['description'] ?? null,
                    'dataset_type' => 'additional_table',
                    'management_mode' => 'operational',
                    'source_name' => $validated['source_name'] ?? $state['original_filename'],
                    'source_format' => strtoupper($state['source_format']),
                    'is_active' => true,
                    'is_spatial' => false,
                    'created_by' => auth()->id(),
                ]);

                foreach ($headers as $sort => $header) {
                    $fieldName = $fieldMap[$header];
                    DatasetField::create([
                        'dataset_id' => $dataset->id,
                        'name' => $fieldName,
                        'display_name' => $header,
                        'data_type' => $fieldTypes[$header],
                        'is_required' => false,
                        'is_unique' => false,
                        'is_identifier' => false,
                        'sort_order' => $sort,
                        'metadata' => ['source_name' => $header, 'source_format' => $state['source_format']],
                    ]);
                }

                $mapping = [];
                foreach ($headers as $header) {
                    $mapping[$header] = $fieldMap[$header];
                }

                $import = DatasetImport::create([
                    'dataset_id' => $dataset->id,
                    'original_filename' => $state['original_filename'],
                    'source_format' => $state['source_format'],
                    'imported_by' => auth()->id(),
                    'started_at' => now(),
                    'status' => 'processing',
                    'total_rows' => 0,
                    'successful_rows' => 0,
                    'failed_rows' => 0,
                ]);

                $uploaded = new \Illuminate\Http\UploadedFile(
                    $path,
                    $state['original_filename'],
                    null,
                    null,
                    true
                );

                $this->importService->import($import, $uploaded, $mapping, $dataset);
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['import' => 'فشل إنشاء الجدول: '.$e->getMessage()])->withInput();
        }

        if (!$dataset) {
            return back()->withErrors(['import' => 'تعذر إنشاء الجدول.'])->withInput();
        }

        $import = $dataset->imports()->latest()->first();

        if (($validated['parent_dataset_id'] ?? null) !== null && $import && $import->successful_rows > 0) {
            $parentDataset = Dataset::findOrFail($validated['parent_dataset_id']);
            $parentField = DatasetField::findOrFail($validated['parent_field_id']);
            $childField = $dataset->fields()->where('display_name', $validated['child_field'])->first()
                ?? $dataset->fields()->where('name', $fieldMap[$validated['child_field']] ?? '')->first();

            $coverage = $this->validateRelationshipCoverage($parentDataset, $parentField, $dataset, $childField);

            if ($coverage['unmatched'] > 0) {
                return redirect()->route('datasets.show', $dataset)
                    ->withErrors([
                        'relationship' => "تم استيراد البيانات، لكن لم يتم إنشاء الربط لأن {$coverage['unmatched']} قيمة في حقل {$validated['child_field']} لا توجد في {$parentDataset->display_name}. راجع القيم ثم أنشئ الربط من صفحة العلاقات.",
                    ]);
            }

            DatasetRelationship::create([
                'parent_dataset_id' => $parentDataset->id,
                'child_dataset_id' => $dataset->id,
                'parent_field_id' => $parentField->id,
                'child_field_id' => $childField->id,
                'relationship_type' => 'one_to_many',
                'on_delete_behavior' => 'restrict',
                'is_nullable' => true,
            ]);
        }

        session()->forget("dataset_imports.{$validated['token']}");
        Storage::disk('local')->delete($state['path']);

        return redirect()->route('datasets.show', $dataset)
            ->with('success', 'تم إنشاء الجدول التشغيلي واستيراد بيانات Excel بنجاح.');
    }

    private function validateRelationshipCoverage(Dataset $parent, DatasetField $parentField, Dataset $child, ?DatasetField $childField): array
    {
        if (!$childField) {
            return ['unmatched' => 1];
        }

        $parentValues = DatasetRecord::where('dataset_id', $parent->id)
            ->pluck('values')
            ->map(fn ($values) => $values[$parentField->name] ?? null)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->flip();

        $unmatched = 0;
        $child->records()->pluck('values')->each(function ($values) use (&$unmatched, $childField, $parentValues) {
            $value = $values[$childField->name] ?? null;
            if ($value !== null && $value !== '' && !$parentValues->has((string) $value)) {
                $unmatched++;
            }
        });

        return ['unmatched' => $unmatched];
    }

    private function buildFieldMap(array $headers): array
    {
        $map = [];
        $used = [];

        foreach ($headers as $header) {
            $base = preg_replace('/[^A-Za-z0-9_]+/', '_', trim($header));
            $base = strtolower(trim($base, '_') ?: 'field');
            $candidate = $base;
            $suffix = 2;

            while (isset($used[$candidate])) {
                $candidate = $base.'_'.$suffix++;
            }

            $used[$candidate] = true;
            $map[$header] = $candidate;
        }

        return $map;
    }
}
