<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetImport;
use App\Models\DatasetRecord;
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
                ->withErrors(['import' => 'Operational data can only be linked to a spatial dataset.']);
        }

        $dataset->load('fields');
        return view('datasets.operational-import', compact('dataset'));
    }

    public function preview(Request $request, Dataset $dataset): \Illuminate\View\View|RedirectResponse
    {
        $this->ensureSpatial($dataset);

        $request->validate(['file' => ['required', 'file', 'mimes:csv,xlsx', 'max:51200']]);
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

            $dataset->load('fields');
            return view('datasets.operational-import-mapping', compact('dataset', 'headers', 'token'));
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
            'match_target_field' => ['required', 'string', 'max:100'],
            'column_mapping' => ['required', 'array'],
            'column_mapping.*' => ['nullable', 'string', 'max:100'],
        ]);

        $state = session()->get("operational_imports.{$validated['token']}");
        abort_unless($state && (int) $state['dataset_id'] === (int) $dataset->id, 404);

        if (!in_array($validated['match_source_column'], $state['headers'], true)) {
            return back()->withErrors(['match_source_column' => 'عمود المطابقة غير موجود في الملف.'])->withInput();
        }

        $fields = $dataset->fields()->get()->keyBy('name');
        if (!$fields->has($validated['match_target_field'])) {
            return back()->withErrors(['match_target_field' => 'حقل المطابقة غير موجود في الطبقة.'])->withInput();
        }

        $mapping = [];
        $mappedTargets = [];
        foreach ($validated['column_mapping'] as $source => $target) {
            if ($target === null || $target === '') continue;
            if (!in_array($source, $state['headers'], true) || !$fields->has($target)) {
                return back()->withErrors(['column_mapping' => 'يوجد ربط أعمدة غير صالح.'])->withInput();
            }
            if ($target === $validated['match_target_field'] && $source !== $validated['match_source_column']) {
                return back()->withErrors(['column_mapping' => 'لا يمكن لعمود آخر استبدال حقل المطابقة.'])->withInput();
            }
            if (isset($mappedTargets[$target])) {
                return back()->withErrors(['column_mapping' => "الحقل {$target} مربوط بأكثر من عمود."])->withInput();
            }
            $mappedTargets[$target] = true;
            $mapping[$source] = $target;
        }

        if (!isset($mapping[$validated['match_source_column']])) {
            $mapping[$validated['match_source_column']] = $validated['match_target_field'];
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
            $result = DB::transaction(function () use ($dataset, $state, $validated, $mapping, $fields, $import, $userId) {
                $rows = $this->parseRows(Storage::disk('local')->path($state['relative_path']), $state['extension']);
                $import->update(['total_rows' => count($rows)]);

                $successful = 0;
                $failed = 0;
                $errors = [];
                $seen = [];

                foreach ($rows as $rowInfo) {
                    $rowNumber = $rowInfo['row'];
                    $row = $rowInfo['data'];
                    try {
                        $matchValue = trim((string) ($row[$validated['match_source_column']] ?? ''));
                        if ($matchValue === '') throw new \RuntimeException('قيمة المطابقة فارغة.');

                        $seenKey = mb_strtolower($matchValue);
                        if (isset($seen[$seenKey])) throw new \RuntimeException("قيمة المطابقة مكررة داخل الملف: {$matchValue}");
                        $seen[$seenKey] = true;

                        $record = DatasetRecord::where('dataset_id', $dataset->id)
                            ->whereRaw("values->>? = ?", [$validated['match_target_field'], $matchValue])
                            ->first();
                        if (!$record) throw new \RuntimeException("لم يتم العثور على سجل مطابق للقيمة: {$matchValue}");

                        $values = $record->values ?? [];
                        foreach ($mapping as $sourceColumn => $targetField) {
                            if ($targetField === $validated['match_target_field'] && $sourceColumn === $validated['match_source_column']) continue;
                            $field = $fields->get($targetField);
                            $values[$targetField] = $this->castValue($row[$sourceColumn] ?? null, $field->data_type);
                        }

                        $record->values = $values;
                        $record->updated_by = $userId;
                        $record->save();
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

                return [$successful, $failed, $errors];
            });

            session()->forget("operational_imports.{$validated['token']}");
            Storage::disk('local')->delete($state['relative_path']);
            return redirect()->route('datasets.show', $dataset)
                ->with('success', "تم ربط البيانات التشغيلية بنجاح. تم تحديث {$result[0]} سجل، وفشل {$result[1]} سجل.");
        } catch (\Throwable $e) {
            $import->update(['status' => 'failed', 'completed_at' => now(), 'error_summary' => [['error' => $e->getMessage()]]]);
            return back()->withErrors(['import' => 'فشل استيراد البيانات التشغيلية: '.$e->getMessage()])->withInput();
        }
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
            $line = fgets($handle); fclose($handle);
            if ($line === false) return [];
            $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
            return array_values(array_map(fn ($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), str_getcsv($line, $delimiter)));
        }
        $sheet = IOFactory::load($path)->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        return array_values(array_map(fn ($v) => trim((string) $v), $sheet->rangeToArray("A1:{$highestColumn}1", null, false, false, false)[0] ?? []));
    }

    private function parseRows(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb'); if (!$handle) throw new \RuntimeException('تعذر فتح CSV.');
            $firstLine = fgets($handle); if ($firstLine === false) { fclose($handle); return []; }
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ','; rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter);
            $headers = array_map(fn ($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), $headers);
            $rows = []; $rowNumber = 1;
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                ++$rowNumber;
                if (count($data) !== count($headers) || count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) continue;
                $rows[] = ['row' => $rowNumber, 'data' => array_combine($headers, $data)];
            }
            fclose($handle); return $rows;
        }

        $sheet = IOFactory::load($path)->getActiveSheet(); $highestColumn = $sheet->getHighestColumn(); $highestRow = $sheet->getHighestRow();
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
        if ($value instanceof DateTimeInterface) return match ($dataType) {'date' => $value->format('Y-m-d'), 'datetime' => $value->format('Y-m-d H:i:s'), default => (string) $value};
        if ($value === null || trim((string) $value) === '') return null;
        $value = trim((string) $value);
        return match ($dataType) {
            'integer' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? throw new \RuntimeException("قيمة integer غير صالحة: {$value}"),
            'decimal' => is_numeric($value) ? (float) $value : throw new \RuntimeException("قيمة decimal غير صالحة: {$value}"),
            'boolean' => match (strtolower($value)) {'true', '1', 'yes', 'y', 'on' => true, 'false', '0', 'no', 'n', 'off' => false, default => throw new \RuntimeException("قيمة boolean غير صالحة: {$value}")},
            'date' => $this->castDate($value),
            'datetime' => $this->castDateTime($value),
            default => $value,
        };
    }

    private function castDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value); $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) throw new \RuntimeException("قيمة التاريخ غير صالحة: {$value}");
        return $date->format('Y-m-d');
    }

    private function castDateTime(string $value): string
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', DATE_ATOM] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value); $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $date->format('Y-m-d H:i:s');
        }
        throw new \RuntimeException("قيمة التاريخ والوقت غير صالحة: {$value}");
    }
}
