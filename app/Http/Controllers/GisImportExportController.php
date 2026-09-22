<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetImport;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Shapefile\Shapefile;
use Shapefile\ShapefileReader;
use Shapefile\ShapefileWriter;
use Shapefile\Geometry\Point;
use Shapefile\Geometry\MultiPoint;
use Shapefile\Geometry\Linestring;
use Shapefile\Geometry\MultiLinestring;
use Shapefile\Geometry\Polygon;
use Shapefile\Geometry\MultiPolygon;

class GisImportExportController extends Controller
{
    public function create(): \Illuminate\View\View
    {
        return view('datasets.import');
    }

    public function preview(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:3', 'max:6'],
            'files.*' => ['required', 'file', 'max:51200'],
        ]);

        $files = collect($request->file('files'));
        $extensions = $files->mapWithKeys(fn ($file) => [strtolower($file->getClientOriginalExtension()) => $file]);
        foreach (['shp', 'shx', 'dbf'] as $required) {
            if (!$extensions->has($required)) {
                return back()->withErrors(['files' => "ملف .$required مطلوب ضمن ملفات الـ Shapefile."])->withInput();
            }
        }

        $bases = $files->map(fn ($file) => Str::lower(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)))->unique();
        if ($bases->count() !== 1) {
            return back()->withErrors(['files' => 'يجب أن تكون جميع ملفات Shapefile من نفس الطبقة ونفس الاسم الأساسي.']);
        }

        $token = (string) Str::uuid();
        $relativeDir = "gis-imports/{$token}";
        foreach ($files as $file) {
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $file->storeAs($relativeDir, $name, 'local');
        }

        try {
            $info = $this->inspectImport(Storage::disk('local')->path($relativeDir));
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($relativeDir);
            return back()->withErrors(['files' => 'تعذر قراءة Shapefile: '.$e->getMessage()])->withInput();
        }

        session()->put("gis_imports.{$token}", [
            'directory' => $relativeDir,
            'shp' => $info['shp'],
            'original_filename' => $files->first(fn ($file) => strtolower($file->getClientOriginalExtension()) === 'shp')->getClientOriginalName(),
        ]);

        return view('datasets.import-preview', compact('token', 'info'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:datasets,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'management_mode' => ['required', 'in:official,web_editable,operational,analytical'],
            'srid' => ['required', 'integer', 'exists:spatial_ref_sys,srid'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'map_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'default_visible' => ['nullable', 'boolean'],
            'map_opacity' => ['nullable', 'numeric', 'between:0,1'],
            'display_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $state = session()->get("gis_imports.{$validated['token']}");
        abort_unless($state, 404);

        $directory = Storage::disk('local')->path($state['directory']);
        if (!is_dir($directory) || !is_file($directory.'/'.$state['shp'])) {
            return back()->withErrors(['token' => 'ملفات الاستيراد المؤقتة غير موجودة.']);
        }

        $import = null;
        try {
            $result = DB::transaction(function () use ($validated, $state, $directory, &$import) {
                $reader = $this->makeReader($directory.'/'.$state['shp']);
                $geometryType = $this->geometryTypeFromShape($reader->getShapeType());
                $prj = $reader->getPRJ();

                if (!$geometryType) {
                    throw new \RuntimeException('نوع Geometry غير مدعوم.');
                }

                $dataset = Dataset::create([
                    'name' => $validated['name'],
                    'display_name' => $validated['display_name'],
                    'description' => $validated['description'] ?? null,
                    'dataset_type' => 'official_layer',
                    'management_mode' => $validated['management_mode'],
                    'source_name' => $validated['source_name'] ?? $state['original_filename'],
                    'source_format' => 'Shapefile',
                    'is_active' => true,
                    'is_spatial' => true,
                    'geometry_type' => $geometryType,
                    'srid' => (int) $validated['srid'],
                    'map_order' => $validated['map_order'] ?? 0,
                    'default_visible' => $validated['default_visible'] ?? true,
                    'map_opacity' => $validated['map_opacity'] ?? 1,
                    'display_color' => $validated['display_color'] ?? '#475467',
                    'created_by' => auth()->id(),
                ]);

                $fields = $this->buildFieldMap($reader);
                foreach ($fields as $field) {
                    DatasetField::create([
                        'dataset_id' => $dataset->id,
                        'name' => $field['name'],
                        'display_name' => $field['display_name'],
                        'data_type' => $field['data_type'],
                        'is_required' => false,
                        'is_unique' => false,
                        'is_identifier' => false,
                        'sort_order' => $field['sort_order'],
                        'metadata' => [
                            'source_name' => $field['source_name'],
                            'source_type' => $field['source_type'],
                        ],
                    ]);
                }

                $import = DatasetImport::create([
                    'dataset_id' => $dataset->id,
                    'original_filename' => $state['original_filename'],
                    'source_format' => 'Shapefile',
                    'imported_by' => auth()->id(),
                    'started_at' => now(),
                    'status' => 'processing',
                ]);

                $total = 0;
                $success = 0;
                $failed = 0;
                $errors = [];

                while ($shape = $reader->fetchRecord()) {
                    if ($shape->isDeleted()) {
                        continue;
                    }
                    ++$total;

                    try {
                        $geometryJson = json_decode($shape->getGeoJSON(false), true, 512, JSON_THROW_ON_ERROR);
                        $wkt = $shape->getWKT();
                        $values = [];
                        foreach ($fields as $field) {
                            $raw = $shape->getData($field['source_name']);
                            $values[$field['name']] = $this->normalizeImportedValue($raw, $field['data_type']);
                        }

                        $valid = DB::selectOne(
                            'SELECT ST_IsValid(ST_GeomFromText(?, ?)) AS valid, ST_GeometryType(ST_GeomFromText(?, ?)) AS geometry_type',
                            [$wkt, $dataset->srid, $wkt, $dataset->srid]
                        );

                        $expected = 'ST_'.$geometryType;
                        if (!$valid->valid || $valid->geometry_type !== $expected) {
                            throw new \RuntimeException('Geometry غير صالح أو لا يطابق نوع الطبقة.');
                        }

                        $record = DatasetRecord::create([
                            'dataset_id' => $dataset->id,
                            'values' => $values,
                            'identifier_value' => $this->identifierValue($values),
                            'created_by' => auth()->id(),
                            'updated_by' => auth()->id(),
                        ]);

                        DB::insert(
                            'INSERT INTO gis_features (dataset_record_id, dataset_id, geometry, geometry_type, srid, created_at, updated_at)
                             VALUES (?, ?, ST_GeomFromText(?, ?), ?, ?, NOW(), NOW())',
                            [$record->id, $dataset->id, $wkt, $dataset->srid, $geometryType, $dataset->srid]
                        );

                        ++$success;
                    } catch (\Throwable $e) {
                        ++$failed;
                        $errors[] = ['record' => $total, 'message' => $e->getMessage()];
                    }
                }

                $status = $failed === 0 ? 'completed' : ($success > 0 ? 'partial' : 'failed');
                $import->update([
                    'completed_at' => now(),
                    'status' => $status,
                    'total_rows' => $total,
                    'successful_rows' => $success,
                    'failed_rows' => $failed,
                    'error_summary' => $errors ? array_slice($errors, 0, 50) : null,
                ]);

                if ($success === 0 && $total > 0) {
                    throw new \RuntimeException('لم يتم استيراد أي معلم بنجاح.');
                }

                return $dataset;
            });

            session()->forget("gis_imports.{$validated['token']}");
            Storage::disk('local')->deleteDirectory($state['directory']);

            return redirect()->route('datasets.show', $result)
                ->with('success', "تم استيراد الطبقة بنجاح. المعالم المستوردة: {$import->successful_rows}.");
        } catch (\Throwable $e) {
            if ($import) {
                $import->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_summary' => [['message' => $e->getMessage()]],
                ]);
            }
            return back()->withErrors(['import' => 'فشل الاستيراد: '.$e->getMessage()])->withInput();
        }
    }

    public function exportGeoJson(Dataset $dataset): JsonResponse
    {
        abort_unless($dataset->isSpatial(), 422);

        $rows = DB::select(
            'SELECT id, ST_AsGeoJSON(ST_Transform(geometry, 4326)) AS geojson
             FROM gis_features WHERE dataset_id = ? ORDER BY id',
            [$dataset->id]
        );

        $features = [];
        foreach ($rows as $row) {
            $record = DatasetRecord::find($this->recordIdForFeature($row->id));
            $features[] = [
                'type' => 'Feature',
                'id' => (int) $row->id,
                'geometry' => json_decode($row->geojson, true),
                'properties' => $record?->values ?? [],
            ];
        }

        return response()
            ->json(['type' => 'FeatureCollection', 'features' => $features, 'crs' => ['type' => 'name', 'properties' => ['name' => 'EPSG:4326']]])
            ->header('Content-Type', 'application/geo+json')
            ->header('Content-Disposition', 'attachment; filename="'.$dataset->name.'.geojson"');
    }

    public function exportCsv(Dataset $dataset): StreamedResponse
    {
        abort_unless($dataset->isSpatial(), 422);
        $fields = $dataset->fields()->orderBy('sort_order')->get();

        return response()->streamDownload(function () use ($dataset, $fields) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_merge($fields->pluck('name')->all(), ['latitude', 'longitude']), ',', '"');

            $rows = DB::select(
                'SELECT id, dataset_record_id, ST_X(ST_Transform(ST_PointOnSurface(geometry), 4326)) AS longitude,
                        ST_Y(ST_Transform(ST_PointOnSurface(geometry), 4326)) AS latitude
                 FROM gis_features WHERE dataset_id = ? ORDER BY id',
                [$dataset->id]
            );

            foreach ($rows as $row) {
                $record = DatasetRecord::find($row->dataset_record_id);
                $values = $record?->values ?? [];
                $line = [];
                foreach ($fields as $field) {
                    $line[] = $values[$field->name] ?? null;
                }
                $line[] = $row->latitude;
                $line[] = $row->longitude;
                fputcsv($handle, $line, ',', '"');
            }
            fclose($handle);
        }, $dataset->name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportShapefile(Dataset $dataset): BinaryFileResponse
    {
        abort_unless($dataset->isSpatial(), 422);

        $dir = storage_path('app/private/gis-exports/'.Str::uuid());
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            abort(500, 'تعذر إنشاء مجلد التصدير.');
        }

        $base = $dir.'/'.$dataset->name;
        $writer = new ShapefileWriter($base.'.shp');
        $writer->setShapeType($this->shapefileTypeForGeometry($dataset->geometry_type));

        $fieldMap = [];
        foreach ($dataset->fields()->orderBy('sort_order')->get() as $field) {
            $name = $this->shapefileFieldName($field->name, $fieldMap);
            $fieldMap[$field->name] = $this->addWriterField($writer, $name, $field->data_type);
        }

        $rows = DB::select(
            'SELECT id, dataset_record_id, ST_AsText(geometry) AS wkt
             FROM gis_features WHERE dataset_id = ? ORDER BY id',
            [$dataset->id]
        );

        foreach ($rows as $row) {
            $geometry = $this->geometryObjectForWkt($row->wkt, $dataset->geometry_type);
            $geometry->initFromWKT($row->wkt);
            $record = DatasetRecord::find($row->dataset_record_id);
            foreach ($fieldMap as $original => $exportName) {
                $geometry->setData($exportName, $record?->values[$original] ?? null);
            }
            $writer->writeRecord($geometry);
        }
        $writer = null;

        $srtext = DB::table('spatial_ref_sys')->where('srid', $dataset->srid)->value('srtext');
        if ($srtext) {
            file_put_contents($base.'.prj', $srtext);
        }

        $zipPath = $dir.'/'.$dataset->name.'.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (glob($base.'.*') as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        return response()->download($zipPath, $dataset->name.'.zip')->deleteFileAfterSend(true);
    }

    private function inspectImport(string $directory): array
    {
        $shp = collect(glob($directory.'/*.shp'))->first();
        if (!$shp) {
            throw new \RuntimeException('ملف SHP غير موجود.');
        }

        $reader = $this->makeReader($shp);
        $shapeType = $reader->getShapeType(Shapefile::FORMAT_STR);
        $geometryType = $this->geometryTypeFromShape($reader->getShapeType());
        $prj = $reader->getPRJ();

        return [
            'shp' => basename($shp),
            'shape_type' => $shapeType,
            'geometry_type' => $geometryType,
            'srid' => $this->detectSrid($prj),
            'prj_present' => (bool) $prj,
            'record_count' => (int) $reader->getTotRecords(),
            'fields' => $reader->getFields(),
            'prj' => $prj,
        ];
    }

    private function makeReader(string $shp): ShapefileReader
    {
        return new ShapefileReader($shp, [
            Shapefile::OPTION_SUPPRESS_M => true,
            Shapefile::OPTION_SUPPRESS_Z => true,
        ]);
    }

    private function geometryTypeFromShape(int $shapeType): ?string
    {
        return match ($shapeType) {
            Shapefile::SHAPE_TYPE_POINT, Shapefile::SHAPE_TYPE_POINTZ, Shapefile::SHAPE_TYPE_POINTM => 'Point',
            Shapefile::SHAPE_TYPE_MULTIPOINT, Shapefile::SHAPE_TYPE_MULTIPOINTZ, Shapefile::SHAPE_TYPE_MULTIPOINTM => 'MultiPoint',
            Shapefile::SHAPE_TYPE_POLYLINE, Shapefile::SHAPE_TYPE_POLYLINEZ, Shapefile::SHAPE_TYPE_POLYLINEM => 'LineString',
            Shapefile::SHAPE_TYPE_POLYGON, Shapefile::SHAPE_TYPE_POLYGONZ, Shapefile::SHAPE_TYPE_POLYGONM => 'Polygon',
            default => null,
        };
    }

    private function detectSrid(?string $prj): ?int
    {
        if (!$prj) {
            return null;
        }
        if (preg_match('/AUTHORITY\s*\[\s*"EPSG"\s*,\s*"?(\d+)"?\s*\]/i', $prj, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/ID\s*\[\s*"EPSG"\s*,\s*(\d+)\s*\]/i', $prj, $m)) {
            return (int) $m[1];
        }
        if (stripos($prj, 'Palestine_1923') !== false && stripos($prj, 'Palestine_1923') !== false) {
            if (stripos($prj, 'Palestine_Grid') !== false || stripos($prj, 'Palestine Grid') !== false) {
                return 28191;
            }
        }
        if (stripos($prj, 'WGS_1984') !== false || stripos($prj, 'WGS 84') !== false) {
            return 4326;
        }
        return null;
    }

    private function buildFieldMap(ShapefileReader $reader): array
    {
        $fields = [];
        $sort = 0;
        foreach ($reader->getFields() as $sourceName => $definition) {
            $name = $this->datasetFieldName($sourceName, $fields);
            $fields[] = [
                'source_name' => $sourceName,
                'name' => $name,
                'display_name' => $sourceName,
                'data_type' => $this->datasetDataType($definition['type'], $definition['decimals'] ?? 0),
                'source_type' => $definition['type'],
                'sort_order' => $sort++,
            ];
        }
        return $fields;
    }

    private function datasetFieldName(string $sourceName, array $existing): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '_', $sourceName);
        $name = trim($name, '_') ?: 'field';
        if (ctype_digit($name[0] ?? '')) {
            $name = 'field_'.$name;
        }
        $base = $name;
        $i = 1;
        while (collect($existing)->contains(fn ($field) => $field['name'] === $name)) {
            $name = $base.'_'.$i++;
        }
        return substr($name, 0, 255);
    }

    private function datasetDataType(string $type, int $decimals): string
    {
        return match ($type) {
            Shapefile::DBF_TYPE_LOGICAL => 'boolean',
            Shapefile::DBF_TYPE_DATE => 'date',
            Shapefile::DBF_TYPE_NUMERIC, Shapefile::DBF_TYPE_FLOAT => $decimals > 0 ? 'decimal' : 'integer',
            default => 'string',
        };
    }

    private function normalizeImportedValue(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'decimal' => (float) $value,
            default => is_scalar($value) ? (string) $value : (string) json_encode($value),
        };
    }

    private function identifierValue(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : null;
            }
        }
        return null;
    }

    private function recordIdForFeature(int $featureId): ?int
    {
        return DB::table('gis_features')->where('id', $featureId)->value('dataset_record_id');
    }

    private function shapefileTypeForGeometry(string $geometryType): int
    {
        return match ($geometryType) {
            'Point' => Shapefile::SHAPE_TYPE_POINT,
            'MultiPoint' => Shapefile::SHAPE_TYPE_MULTIPOINT,
            'LineString' => Shapefile::SHAPE_TYPE_POLYLINE,
            'MultiLineString' => Shapefile::SHAPE_TYPE_POLYLINE,
            'Polygon' => Shapefile::SHAPE_TYPE_POLYGON,
            'MultiPolygon' => Shapefile::SHAPE_TYPE_POLYGON,
            default => abort(422, 'نوع Geometry غير مدعوم للتصدير.'),
        };
    }

    private function geometryObjectForWkt(string $wkt, string $datasetGeometryType): object
    {
        $upper = strtoupper(ltrim($wkt));

        return match (true) {
            str_starts_with($upper, 'MULTIPOINT') => new MultiPoint(),
            str_starts_with($upper, 'MULTILINESTRING') => new MultiLinestring(),
            str_starts_with($upper, 'MULTIPOLYGON') => new MultiPolygon(),
            str_starts_with($upper, 'POINT') => new Point(),
            str_starts_with($upper, 'LINESTRING') => new Linestring(),
            str_starts_with($upper, 'POLYGON') => new Polygon(),
            default => $this->geometryObjectForType($datasetGeometryType),
        };
    }

    private function geometryObjectForType(string $geometryType): object
    {
        return match ($geometryType) {
            'Point' => new Point(),
            'MultiPoint' => new MultiPoint(),
            'LineString' => new Linestring(),
            'MultiLineString' => new MultiLinestring(),
            'Polygon' => new Polygon(),
            'MultiPolygon' => new MultiPolygon(),
            default => abort(422, 'نوع Geometry غير مدعوم للتصدير.'),
        };
    }

    private function shapefileFieldName(string $name, array $existing): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        $base = substr(trim($base, '_') ?: 'FIELD', 0, 10);
        $candidate = $base;
        $i = 1;
        while (in_array($candidate, $existing, true)) {
            $suffix = '_'.$i++;
            $candidate = substr($base, 0, 10 - strlen($suffix)).$suffix;
        }
        return $candidate;
    }

    private function addWriterField(ShapefileWriter $writer, string $name, string $type): string
    {
        return match ($type) {
            'integer' => $writer->addNumericField($name, 18, 0),
            'decimal' => $writer->addFloatField($name, 20, 6),
            'boolean' => $writer->addLogicalField($name),
            'date' => $writer->addDateField($name),
            default => $writer->addCharField($name, 254),
        };
    }
}
