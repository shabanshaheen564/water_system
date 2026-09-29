<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetImport;
use App\Models\DatasetRecord;
use App\Models\DatasetRelationship;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OperationalDataImportWebController extends Controller
{
    public function create(Dataset $dataset): \Illuminate\View\View|RedirectResponse
    {
        if (!$dataset->isSpatial()) {
            return redirect()->route('datasets.show', $dataset)
                ->withErrors(['import' => 'البيانات التشغيلية يمكن ربطها بطبقة مكانية فقط.']);
        }

        $dataset->load('fields');
        $identifierField = $dataset->getIdentifierField();

        if (!$identifierField) {
            return redirect()->route('datasets.show', $dataset)
                ->withErrors(['import' => 'يجب تعريف حقل Identifier في الطبقة الأساسية قبل استيراد البيانات التشغيلية.']);
        }

        return view('datasets.operational-import', compact('dataset', 'identifierField'));
    }

    public function updateCreate(Dataset $dataset): \Illuminate\View\View|RedirectResponse
    {
        $relationship = $this->operationalRelationship($dataset);

        if (!$relationship) {
            return redirect()->route('datasets.show', $dataset)
                ->withErrors(['import' => 'هذه المجموعة ليست جدولاً تشغيلياً مرتبطاً بطبقة أساسية.']);
        }

        return view('datasets.operational-import', [
            'dataset' => $dataset,
            'parentDataset' => $relationship->parentDataset,
            'identifierField' => $relationship->parentField,
            'updateMode' => true,
        ]);
    }

    public function updatePreview(Request $request, Dataset $dataset): \Illuminate\View\View|RedirectResponse
    {
        $relationship = $this->operationalRelationship($dataset);

        if (!$relationship) {
            return redirect()->route('datasets.show', $dataset)
                ->withErrors(['import' => 'لا توجد علاقة تشغيلية صالحة لهذا الجدول.']);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xls,xlsx,xlsm,xlt,xltx', 'max:51200'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $token = (string) Str::uuid();
        $relativePath = 'operational-imports/'.$token.'.'.$extension;
        $file->storeAs('operational-imports', $token.'.'.$extension, 'local');

        try {
            $headers = $this->parseHeaders(Storage::disk('local')->path($relativePath), $extension);
            if ($headers === [] || in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
                throw new \RuntimeException('ملف الاستيراد يحتوي على عناوين أعمدة فارغة أو مكررة.');
            }

            session()->put("operational_import_updates.{$token}", [
                'dataset_id' => $dataset->id,
                'parent_dataset_id' => $relationship->parent_dataset_id,
                'relative_path' => $relativePath,
                'original_filename' => $file->getClientOriginalName(),
                'extension' => $extension,
                'headers' => $headers,
            ]);

            return view('datasets.operational-import-mapping', [
                'dataset' => $dataset,
                'parentDataset' => $relationship->parentDataset,
                'identifierField' => $relationship->parentField,
                'childField' => $relationship->childField,
                'headers' => $headers,
                'token' => $token,
                'updateMode' => true,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($relativePath);
            return back()->withErrors(['file' => 'تعذر قراءة الملف: '.$e->getMessage()]);
        }
    }

    public function updateConfirm(Request $request, Dataset $dataset): RedirectResponse
    {
        $relationship = $this->operationalRelationship($dataset);

        if (!$relationship) {
            return redirect()->route('datasets.show', $dataset)
                ->withErrors(['import' => 'لا توجد علاقة تشغيلية صالحة لهذا الجدول.']);
        }

        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'match_source_column' => ['required', 'string', 'max:100'],
            'import_columns' => ['sometimes', 'array'],
            'import_columns.*' => ['nullable', 'boolean'],
        ]);

        $state = session()->get("operational_import_updates.{$validated['token']}");
        abort_unless($state && (int) $state['dataset_id'] === (int) $dataset->id, 404);

        if (!in_array($validated['match_source_column'], $state['headers'], true)) {
            return back()->withErrors(['match_source_column' => 'عمود المطابقة غير موجود في الملف.'])->withInput();
        }

        // A checked checkbox is represented by the presence of its key in the
        // import_columns array. Do not depend on the checkbox value itself.
        // This keeps update selection reliable for browser forms and direct requests.
        $selectedColumns = [];
        $importColumns = $request->input('import_columns', []);
        $importColumns = is_array($importColumns) ? $importColumns : [];

        foreach ($state['headers'] as $header) {
            if ($header === $validated['match_source_column'] || array_key_exists($header, $importColumns)) {
                $selectedColumns[] = $header;
            }
        }

        $userId = $request->user()->id;
        $import = DatasetImport::create([
            'dataset_id' => $dataset->id,
            'original_filename' => $state['original_filename'],
            'source_format' => $state['extension'],
            'imported_by' => $userId,
            'started_at' => now(),
            'status' => 'processing',
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ]);

        try {
            $result = DB::transaction(function () use ($dataset, $relationship, $state, $validated, $selectedColumns, $import, $userId) {
                $rows = $this->parseRows(Storage::disk('local')->path($state['relative_path']), $state['extension']);
                $import->update(['total_rows' => count($rows)]);

                $parentDataset = $relationship->parentDataset;
                $identifierField = $relationship->parentField;
                $childFields = $this->ensureSupportingFields(
                    $dataset,
                    $selectedColumns,
                    $validated['match_source_column'],
                    $identifierField,
                    $rows
                );

                $successful = 0;
                $created = 0;
                $updated = 0;
                $failed = 0;
                $errors = [];
                $seen = [];

                foreach ($rows as $rowInfo) {
                    $rowNumber = $rowInfo['row'];
                    $row = $rowInfo['data'];

                    try {
                        $matchValue = trim((string) ($row[$validated['match_source_column']] ?? ''));
                        if ($matchValue === '') {
                            throw new \RuntimeException('قيمة الربط فارغة.');
                        }
                        if (isset($seen[$matchValue])) {
                            throw new \RuntimeException("مفتاح الربط مكرر داخل الملف: {$matchValue}");
                        }
                        $seen[$matchValue] = true;

                        $parentRecord = DatasetRecord::where('dataset_id', $parentDataset->id)
                            ->whereRaw("values->>? = ?", [$identifierField->name, $matchValue])
                            ->first();

                        if (!$parentRecord) {
                            throw new \RuntimeException("لم يتم العثور على سجل في الطبقة الأساسية للقيمة: {$matchValue}");
                        }

                        $values = [];
                        foreach ($selectedColumns as $sourceColumn) {
                            $field = $childFields[$sourceColumn];
                            $values[$field->name] = $this->castValue($row[$sourceColumn] ?? null, $field->data_type);
                        }

                        // The relationship and the persisted identifier_value form the
                        // contract for an operational row. Never create a second row merely
                        // because a legacy import stored the key under a different JSON field.
                        $childField = $relationship->childField;
                        $sourceField = $childFields[$validated['match_source_column']] ?? null;

                        $candidateFieldNames = collect([
                            $childField?->name,
                            $sourceField?->name,
                        ])->filter()->unique()->values()->all();

                        // Also include fields that explicitly declare the same source column.
                        // This covers older operational datasets whose field name changed while
                        // the relationship still points at the original field definition.
                        $metadataFieldNames = $dataset->fields()
                            ->get()
                            ->filter(fn (DatasetField $field) =>
                                ($field->metadata['source_column'] ?? null) === $validated['match_source_column']
                            )
                            ->pluck('name')
                            ->all();

                        $candidateFieldNames = array_values(array_unique([
                            ...$candidateFieldNames,
                            ...$metadataFieldNames,
                        ]));

                        $records = DatasetRecord::where('dataset_id', $dataset->id)
                            ->lockForUpdate()
                            ->get();

                        $matches = $records->filter(function (DatasetRecord $record) use ($matchValue, $candidateFieldNames): bool {
                            if (trim((string) $record->identifier_value) === $matchValue) {
                                return true;
                            }

                            $values = $record->values ?? [];

                            foreach ($candidateFieldNames as $fieldName) {
                                if (trim((string) ($values[$fieldName] ?? '')) === $matchValue) {
                                    return true;
                                }
                            }

                            return false;
                        })->values();

                        if ($matches->count() > 1) {
                            throw new \RuntimeException("يوجد أكثر من سجل تشغيلي بنفس مفتاح الربط: {$matchValue}");
                        }

                        $childRecord = $matches->first();

                        if ($childRecord) {
                            // Update the existing record through the model attributes so the
                            // JSON cast is applied deterministically and the normal Eloquent
                            // observers/audit trail remain active.
                            $mergedValues = array_replace($childRecord->values ?? [], $values);

                            // Persist the complete JSON document directly. This avoids any
                            // ambiguity around dirty-state detection for the JSON/JSONB
                            // attribute while keeping the update scoped to this exact record.
                            $affected = DB::table('dataset_records')
                                ->where('id', $childRecord->id)
                                ->where('dataset_id', $dataset->id)
                                ->update([
                                    'values' => json_encode($mergedValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                    'identifier_value' => $matchValue,
                                    'updated_by' => $userId,
                                    'updated_at' => now(),
                                ]);

                            if ($affected !== 1) {
                                throw new \RuntimeException('تعذر تحديث السجل التشغيلي الموجود.');
                            }

                            $childRecord->refresh();

                            // Verify the persisted database state before counting this row
                            // as updated.
                            foreach ($values as $fieldName => $newValue) {
                                if ((($childRecord->values ?? [])[$fieldName] ?? null) !== $newValue) {
                                    throw new \RuntimeException("تعذر حفظ الحقل التشغيلي: {$fieldName}");
                                }
                            }

                            ++$updated;
                        } else {
                            DatasetRecord::create([
                                'dataset_id' => $dataset->id,
                                'values' => $values,
                                'identifier_value' => $matchValue,
                                'created_by' => $userId,
                            ]);
                            ++$created;
                        }

                        ++$successful;
                    } catch (\Throwable $e) {
                        ++$failed;
                        $errors[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
                    }
                }

                $status = match (true) {
                    $successful > 0 && $failed > 0 => 'partial',
                    $successful > 0 => 'completed',
                    default => 'failed',
                };

                $import->update([
                    'status' => $status,
                    'successful_rows' => $successful,
                    'failed_rows' => $failed,
                    'completed_at' => now(),
                    'error_summary' => $errors ? array_slice($errors, 0, 50) : null,
                ]);

                return [$successful, $created, $updated, $failed];
            });

            session()->forget("operational_import_updates.{$validated['token']}");
            Storage::disk('local')->delete($state['relative_path']);

            return redirect()->route('datasets.show', $dataset)
                ->with('success', "تم تحديث الجدول «{$dataset->name}». تمت معالجة {$result[0]} سجل: {$result[1]} جديد، {$result[2]} محدث، وفشل {$result[3]}.");
        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_summary' => [['error' => $e->getMessage()]],
            ]);

            return back()->withErrors(['import' => 'فشل تحديث البيانات التشغيلية: '.$e->getMessage()])->withInput();
        }
    }

    public function preview(Request $request, Dataset $dataset): \Illuminate\View\View|RedirectResponse
    {
        $this->ensureSpatial($dataset);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xls,xlsx,xlsm,xlt,xltx', 'max:51200'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $token = (string) Str::uuid();
        $relativePath = 'operational-imports/'.$token.'.'.$extension;
        $file->storeAs('operational-imports', $token.'.'.$extension, 'local');

        try {
            $headers = $this->parseHeaders(Storage::disk('local')->path($relativePath), $extension);
            if ($headers === [] || in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
                throw new \RuntimeException('ملف الاستيراد يحتوي على عناوين أعمدة فارغة أو مكررة.');
            }

            session()->put("operational_imports.{$token}", [
                'dataset_id' => $dataset->id,
                'relative_path' => $relativePath,
                'original_filename' => $file->getClientOriginalName(),
                'extension' => $extension,
                'headers' => $headers,
            ]);

            $identifierField = $dataset->getIdentifierField();
            return view('datasets.operational-import-mapping', compact('dataset', 'identifierField', 'headers', 'token'));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($relativePath);
            return back()->withErrors(['file' => 'تعذر قراءة الملف: '.$e->getMessage()]);
        }
    }

    public function confirm(Request $request, Dataset $dataset): RedirectResponse
    {
        $this->ensureSpatial($dataset);

        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'match_source_column' => ['required', 'string', 'max:100'],
            'import_columns' => ['sometimes', 'array'],
            'import_columns.*' => ['nullable', 'boolean'],
        ]);

        $state = session()->get("operational_imports.{$validated['token']}");
        abort_unless($state && (int) $state['dataset_id'] === (int) $dataset->id, 404);

        $identifierField = $dataset->getIdentifierField();
        if (!$identifierField) {
            return back()->withErrors(['match_source_column' => 'الطبقة لا تحتوي على حقل Identifier صالح.'])->withInput();
        }

        if (!in_array($validated['match_source_column'], $state['headers'], true)) {
            return back()->withErrors(['match_source_column' => 'عمود المطابقة غير موجود في الملف.'])->withInput();
        }

        $selectedColumns = [];
        foreach ($state['headers'] as $header) {
            if ($header === $validated['match_source_column'] || !empty($validated['import_columns'][$header])) {
                $selectedColumns[] = $header;
            }
        }

        if (!in_array($validated['match_source_column'], $selectedColumns, true)) {
            $selectedColumns[] = $validated['match_source_column'];
        }

        $userId = $request->user()->id;
        $import = DatasetImport::create([
            'dataset_id' => $dataset->id,
            'original_filename' => $state['original_filename'],
            'source_format' => $state['extension'],
            'imported_by' => $userId,
            'started_at' => now(),
            'status' => 'processing',
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ]);

        try {
            $result = DB::transaction(function () use ($dataset, $state, $validated, $selectedColumns, $identifierField, $import, $userId) {
                $rows = $this->parseRows(Storage::disk('local')->path($state['relative_path']), $state['extension']);
                $import->update(['total_rows' => count($rows)]);

                $supportingDataset = $this->getOrCreateSupportingDataset($dataset, $state['original_filename'], $state['extension'], $userId);
                $import->update(['dataset_id' => $supportingDataset->id]);
                $childFields = $this->ensureSupportingFields(
                    $supportingDataset,
                    $selectedColumns,
                    $validated['match_source_column'],
                    $identifierField,
                    $rows
                );

                $relationship = DatasetRelationship::firstOrCreate(
                    [
                        'parent_dataset_id' => $dataset->id,
                        'child_dataset_id' => $supportingDataset->id,
                        'parent_field_id' => $identifierField->id,
                        'child_field_id' => $childFields[$validated['match_source_column']]->id,
                    ],
                    [
                        'relationship_type' => 'one_to_many',
                        'on_delete_behavior' => 'restrict',
                        'is_nullable' => false,
                    ]
                );

                $successful = 0;
                $failed = 0;
                $errors = [];
                $seen = [];

                foreach ($rows as $rowInfo) {
                    $rowNumber = $rowInfo['row'];
                    $row = $rowInfo['data'];

                    try {
                        $matchValue = trim((string) ($row[$validated['match_source_column']] ?? ''));
                        if ($matchValue === '') {
                            throw new \RuntimeException('قيمة الربط فارغة.');
                        }
                        if (isset($seen[$matchValue])) {
                            throw new \RuntimeException("مفتاح الربط مكرر داخل الملف: {$matchValue}");
                        }
                        $seen[$matchValue] = true;

                        $parentRecord = DatasetRecord::where('dataset_id', $dataset->id)
                            ->whereRaw("values->>? = ?", [$identifierField->name, $matchValue])
                            ->first();

                        if (!$parentRecord) {
                            throw new \RuntimeException("لم يتم العثور على سجل في الطبقة الأساسية للقيمة: {$matchValue}");
                        }

                        $values = [];
                        foreach ($selectedColumns as $sourceColumn) {
                            $field = $childFields[$sourceColumn];
                            $values[$field->name] = $this->castValue($row[$sourceColumn] ?? null, $field->data_type);
                        }

                        DatasetRecord::create([
                            'dataset_id' => $supportingDataset->id,
                            'values' => $values,
                            'identifier_value' => $matchValue,
                            'created_by' => $userId,
                        ]);

                        ++$successful;
                    } catch (\Throwable $e) {
                        ++$failed;
                        $errors[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
                    }
                }

                $status = match (true) {
                    $successful > 0 && $failed > 0 => 'partial',
                    $successful > 0 => 'completed',
                    default => 'failed',
                };

                $import->update([
                    'status' => $status,
                    'successful_rows' => $successful,
                    'failed_rows' => $failed,
                    'completed_at' => now(),
                    'error_summary' => $errors ? array_slice($errors, 0, 50) : null,
                ]);

                return [$successful, $failed, $errors, $supportingDataset->display_name];
            });

            session()->forget("operational_imports.{$validated['token']}");
            Storage::disk('local')->delete($state['relative_path']);

            return redirect()->route('datasets.show', $dataset)
                ->with('success', "تم حفظ البيانات في الجدول الداعم «{$result[3]}». أضيف {$result[0]} سجل، وفشل {$result[1]} سجل. لم يتم تعديل Geometry أو حقول الطبقة الأساسية.");
        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_summary' => [['error' => $e->getMessage()]],
            ]);

            return back()->withErrors(['import' => 'فشل استيراد البيانات التشغيلية: '.$e->getMessage()])->withInput();
        }
    }

    private function getOrCreateSupportingDataset(Dataset $parent, string $filename, string $extension, int $userId): Dataset
    {
        $fileBase = pathinfo($filename, PATHINFO_FILENAME);
        $baseName = Str::snake(Str::ascii($parent->name.'_'.$fileBase));
        $baseName = preg_replace('/[^a-zA-Z0-9_]/', '_', $baseName) ?: 'operational_data';
        $baseName = trim($baseName, '_') ?: 'operational_data';

        $candidate = $baseName;
        $counter = 2;

        while (Dataset::where('name', $candidate)->exists()) {
            $candidate = $baseName.'_'.$counter++;
        }

        return Dataset::create([
            'name' => $candidate,
            'display_name' => $fileBase ?: $candidate,
            'description' => 'جدول داعم للبيانات التشغيلية المرتبطة بطبقة '.$parent->display_name.' — مصدر الملف: '.$filename,
            'dataset_type' => 'additional_table',
            'management_mode' => 'operational',
            'source_name' => $filename,
            'source_format' => $extension,
            'is_active' => true,
            'is_spatial' => false,
            'geometry_type' => null,
            'srid' => null,
            'map_order' => 0,
            'default_visible' => false,
            'map_opacity' => 1,
            'display_color' => '#475467',
            'created_by' => $userId,
        ]);
    }

    private function ensureSupportingFields(Dataset $supportingDataset, array $headers, string $matchSourceColumn, DatasetField $parentIdentifierField, array $rows): array
    {
        $fields = $supportingDataset->fields()->get()->keyBy('name');
        $result = [];

        foreach ($headers as $header) {
            $existingField = $fields->first(fn (DatasetField $field) => ($field->metadata['source_column'] ?? null) === $header || $field->name === Str::snake(Str::ascii($header)));
            $name = $existingField?->name ?? $this->fieldNameForHeader($header, $fields->keys()->all());
            if ($header === $matchSourceColumn) {
                $name = $existingField?->name ?? $parentIdentifierField->name;
                $type = $parentIdentifierField->data_type;
                $displayName = $parentIdentifierField->display_name;
            } else {
                $type = $this->inferDataType($rows, $header);
                $displayName = $header;
            }

            $field = $existingField ?? $fields->get($name);

            if (!$field) {
                $field = $supportingDataset->fields()->create([
                    'name' => $name,
                    'display_name' => $displayName,
                    'data_type' => $type,
                    'is_required' => $header === $matchSourceColumn,
                    'is_unique' => false,
                    'is_identifier' => false,
                    'default_value' => null,
                    'sort_order' => $supportingDataset->fields()->max('sort_order') + 1,
                    'metadata' => ['source_column' => $header],
                ]);
                $fields->put($name, $field);
            }

            $result[$header] = $field;
        }

        return $result;
    }

    private function fieldNameForHeader(string $header, array $existingNames, ?string $preferred = null): string
    {
        if ($preferred) return $preferred;
        $name = Str::snake(Str::ascii($header));
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?: 'field';
        $name = trim($name, '_');
        if ($name === '') $name = 'field';

        $candidate = $name;
        $counter = 2;
        while (in_array($candidate, $existingNames, true)) {
            $candidate = $name.'_'.$counter++;
        }

        return $candidate;
    }

    private function inferDataType(array $rows, string $header): string
    {
        $values = [];
        foreach ($rows as $rowInfo) {
            $value = $rowInfo['data'][$header] ?? null;
            if ($value instanceof DateTimeInterface) return 'datetime';
            if ($value !== null && trim((string) $value) !== '') $values[] = trim((string) $value);
            if (count($values) >= 20) break;
        }

        if ($values === []) return 'string';
        if (collect($values)->every(fn ($v) => filter_var($v, FILTER_VALIDATE_INT) !== false)) return 'integer';
        if (collect($values)->every(fn ($v) => is_numeric($v))) return 'decimal';
        if (collect($values)->every(fn ($v) => in_array(strtolower($v), ['true','false','yes','no','1','0'], true))) return 'boolean';

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            $valid = collect($values)->every(function ($value) use ($format) {
                $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
                $errors = DateTimeImmutable::getLastErrors();
                return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
            });
            if ($valid) return 'date';
        }

        return 'string';
    }

    /**
     * Locate the single operational record represented by a relationship key.
     * The database identifier_value is canonical, while JSON field names remain
     * compatible with records created by older imports.
     */
    private function findOperationalRecord(
        Dataset $dataset,
        DatasetRelationship $relationship,
        DatasetField $sourceField,
        string $matchSourceColumn,
        string $matchValue
    ): ?DatasetRecord {
        $candidateFieldNames = collect([
            $relationship->childField?->name,
            $sourceField->name,
        ])->filter()->unique()->values()->all();

        $metadataFieldNames = $dataset->fields()
            ->get()
            ->filter(fn (DatasetField $field) =>
                ($field->metadata['source_column'] ?? null) === $matchSourceColumn
            )
            ->pluck('name')
            ->all();

        $candidateFieldNames = array_values(array_unique([
            ...$candidateFieldNames,
            ...$metadataFieldNames,
        ]));

        $matches = DatasetRecord::where('dataset_id', $dataset->id)
            ->lockForUpdate()
            ->get()
            ->filter(function (DatasetRecord $record) use ($matchValue, $candidateFieldNames): bool {
                if (trim((string) $record->identifier_value) === $matchValue) {
                    return true;
                }

                foreach (($record->values ?? []) as $fieldName => $value) {
                    if (in_array($fieldName, $candidateFieldNames, true)
                        && trim((string) $value) === $matchValue) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        if ($matches->count() > 1) {
            throw new \RuntimeException("يوجد أكثر من سجل تشغيلي بنفس مفتاح الربط: {$matchValue}");
        }

        return $matches->first();
    }

    private function operationalRelationship(Dataset $dataset): ?DatasetRelationship
    {
        if ($dataset->dataset_type !== 'additional_table' || $dataset->management_mode !== 'operational') {
            return null;
        }

        return $dataset->childRelationships()
            ->with(['parentDataset', 'parentField', 'childField'])
            ->first();
    }

    private function ensureSpatial(Dataset $dataset): void
    {
        abort_unless($dataset->isSpatial(), 422, 'This dataset is not configured as spatial.');
    }

    private function parseHeaders(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb');
            if (!$handle) throw new \RuntimeException('تعذر فتح CSV.');
            $line = fgets($handle);
            fclose($handle);
            if ($line === false) return [];
            $delimiter = $this->detectCsvDelimiter($line);
            return array_values(array_map(fn ($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), str_getcsv($line, $delimiter)));
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();

        return array_values(array_map(
            fn ($v) => trim((string) $v),
            $sheet->rangeToArray("A1:{$highestColumn}1", null, false, false, false)[0] ?? []
        ));
    }

    private function detectCsvDelimiter(string $line): string
    {
        $delimiters = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($delimiters);
        return array_key_first($delimiters) ?? ',';
    }

    private function parseRows(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb');
            if (!$handle) throw new \RuntimeException('تعذر فتح CSV.');
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);
                return [];
            }

            $delimiter = $this->detectCsvDelimiter($firstLine);
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter);
            $headers = array_map(fn ($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), $headers);
            $rows = [];
            $rowNumber = 1;

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                ++$rowNumber;
                if (count($data) !== count($headers) || count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) continue;
                $rows[] = ['row' => $rowNumber, 'data' => array_combine($headers, $data)];
            }

            fclose($handle);
            return $rows;
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();
        $headers = array_map(fn ($v) => trim((string) $v), $sheet->rangeToArray("A1:{$highestColumn}1", null, false, false, false)[0] ?? []);
        $rows = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; ++$rowNumber) {
            $data = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, false, false, false)[0] ?? [];
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) continue;
            $rows[] = ['row' => $rowNumber, 'data' => array_combine($headers, $data)];
        }

        return $rows;
    }

    private function castValue(mixed $value, string $dataType): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return match ($dataType) {
                'date' => $value->format('Y-m-d'),
                'datetime' => $value->format('Y-m-d H:i:s'),
                default => (string) $value,
            };
        }

        if ($value === null || trim((string) $value) === '') return null;
        $value = trim((string) $value);

        return match ($dataType) {
            'integer' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? throw new \RuntimeException("قيمة integer غير صالحة: {$value}"),
            'decimal' => is_numeric($value) ? (float) $value : throw new \RuntimeException("قيمة decimal غير صالحة: {$value}"),
            'boolean' => match (strtolower($value)) {
                'true', '1', 'yes', 'y', 'on' => true,
                'false', '0', 'no', 'n', 'off' => false,
                default => throw new \RuntimeException("قيمة boolean غير صالحة: {$value}"),
            },
            'date' => $this->castDate($value),
            'datetime' => $this->castDateTime($value),
            default => $value,
        };
    }

    private function castDate(string $value): string
    {
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        throw new \RuntimeException("قيمة التاريخ غير صالحة: {$value}");
    }

    private function castDateTime(string $value): string
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', DATE_ATOM] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 || $errors['error_count'] === 0))) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new \RuntimeException("قيمة التاريخ والوقت غير صالحة: {$value}");
    }
}
