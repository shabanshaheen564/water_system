<?php

namespace App\Http\Controllers;

use App\Http\Requests\GisFeature\StoreGisFeatureRequest;
use App\Http\Requests\GisFeature\UpdateGisFeatureRequest;
use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GisFeatureController extends Controller
{
    public function index(Request $request, Dataset $dataset): JsonResponse
    {
        if (!$dataset->isSpatial()) return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        $query = GisFeature::where('dataset_id', $dataset->id)->with(['datasetRecord:id,values,identifier_value']);
        $datasetSrid = (int) ($dataset->srid ?? 4326);
        if ($request->has('bbox')) {
            $bbox = explode(',', $request->bbox);
            if (count($bbox) === 4) {
                [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $bbox);
                $envelopeSql = $datasetSrid == 4326 ? 'ST_MakeEnvelope(?, ?, ?, ?, 4326)' : 'ST_Transform(ST_MakeEnvelope(?, ?, ?, ?, 4326), ?::integer)';
                $bindings = [$minLng, $minLat, $maxLng, $maxLat];
                if ($datasetSrid != 4326) $bindings[] = $datasetSrid;
                $query->whereRaw("geometry && {$envelopeSql}", $bindings);
            }
        }
        if ($request->has(['lat', 'lng', 'radius'])) {
            $lat = $request->float('lat'); $lng = $request->float('lng'); $radius = $request->float('radius');
            if ($datasetSrid == 4326) $query->whereRaw('ST_DWithin(geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radius]);
            else $query->whereRaw('ST_DWithin(geometry, ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), ?::integer), ?)', [$lng, $lat, $datasetSrid, $radius]);
        }
        $features = $query->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 100), 1), 500));
        return response()->json(['type' => 'FeatureCollection', 'features' => $features->getCollection()->map(fn ($feature) => $feature->toGeoJsonFeature())->values(), 'links' => ['first' => $features->url(1), 'last' => $features->url($features->lastPage()), 'prev' => $features->previousPageUrl(), 'next' => $features->nextPageUrl()], 'meta' => ['current_page' => $features->currentPage(), 'from' => $features->firstItem(), 'last_page' => $features->lastPage(), 'path' => $features->path(), 'per_page' => $features->perPage(), 'to' => $features->lastItem(), 'total' => $features->total()]]);
    }

    public function store(StoreGisFeatureRequest $request, Dataset $dataset): JsonResponse
    {
        $this->ensureWebEditableDataset($dataset);
        if (!$dataset->isSpatial()) return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        $validated = $request->validated();
        $geometryType = $validated['geometry']['type'];
        if ($dataset->geometry_type && $geometryType !== $dataset->geometry_type) return response()->json(['message' => "Geometry type must be {$dataset->geometry_type} for this dataset."], 422);

        $creatingRecord = !array_key_exists('dataset_record_id', $validated);
        $record = $creatingRecord
            ? null
            : DatasetRecord::where('id', $validated['dataset_record_id'])->where('dataset_id', $dataset->id)->firstOrFail();

        if ($record && GisFeature::where('dataset_record_id', $record->id)->exists()) {
            return response()->json(['message' => 'A GIS feature already exists for this record.'], 422);
        }

        return DB::transaction(function () use ($validated, $dataset, $record, $geometryType, $creatingRecord, $request) {
            $geojson = json_encode(['type' => $geometryType, 'coordinates' => $validated['geometry']['coordinates']]);
            $srid = $dataset->srid ?? 4326;
            $geometryResult = DB::selectOne(
                'SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?::integer) as geometry, ST_IsValid(ST_SetSRID(ST_GeomFromGeoJSON(?), ?::integer)) as is_valid',
                [$geojson, $srid, $geojson, $srid]
            );

            if (!$geometryResult->is_valid) {
                return response()->json(['message' => 'The supplied geometry is not valid.'], 422);
            }

            $geometry = $geometryResult->geometry;

            if ($creatingRecord) {
                $values = $this->applyDefaults($dataset, $validated['values'] ?? []);
                $this->validateChildReferences($dataset, $values);

                $identifier = $dataset->getIdentifierField();
                $record = DatasetRecord::create([
                    'dataset_id' => $dataset->id,
                    'values' => $values,
                    'identifier_value' => $identifier ? (isset($values[$identifier->name]) ? (string) $values[$identifier->name] : null) : null,
                    'created_by' => $request->user()->id,
                ]);
            }

            $feature = GisFeature::create(['dataset_record_id' => $record->id, 'dataset_id' => $dataset->id, 'geometry' => $geometry, 'geometry_type' => $geometryType, 'srid' => $srid]);
            $feature->load('datasetRecord:id,values,identifier_value');
            return response()->json($feature->toGeoJsonFeature(), 201);
        });
    }

    public function show(Dataset $dataset, GisFeature $feature): JsonResponse
    {
        $this->ensureFeatureBelongsToDataset($dataset, $feature);
        $feature->load('datasetRecord:id,values,identifier_value');
        return response()->json($feature->toGeoJsonFeature());
    }

    public function update(UpdateGisFeatureRequest $request, Dataset $dataset, GisFeature $feature): JsonResponse
    {
        $this->ensureFeatureBelongsToDataset($dataset, $feature);
        $this->ensureWebEditableDataset($dataset);
        if (!$dataset->isSpatial()) return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);

        $validated = $request->validated();
        $feature->load('datasetRecord');

        return DB::transaction(function () use ($validated, $feature, $dataset, $request) {
            if (isset($validated['values'])) {
                $values = array_merge($feature->datasetRecord?->values ?? [], $validated['values']);
                $this->validateChildReferences($dataset, $values);

                $identifier = $dataset->getIdentifierField();
                $feature->datasetRecord->update([
                    'values' => $values,
                    'identifier_value' => $identifier
                        ? (array_key_exists($identifier->name, $values) ? (string) $values[$identifier->name] : null)
                        : null,
                    'updated_by' => $request->user()->id,
                ]);
            }

            if (isset($validated['geometry'])) {
                $geometryType = $validated['geometry']['type'];
                if ($dataset->geometry_type && $geometryType !== $dataset->geometry_type) {
                    return response()->json(['message' => "Geometry type must be {$dataset->geometry_type} for this dataset."], 422);
                }

                $geojson = json_encode([
                    'type' => $geometryType,
                    'coordinates' => $validated['geometry']['coordinates'],
                ]);
                $srid = $dataset->srid ?? 4326;
                $geometryResult = DB::selectOne(
                    'SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?::integer) as geometry, ST_IsValid(ST_SetSRID(ST_GeomFromGeoJSON(?), ?::integer)) as is_valid',
                    [$geojson, $srid, $geojson, $srid]
                );

                if (!$geometryResult->is_valid) {
                    return response()->json(['message' => 'The supplied geometry is not valid.'], 422);
                }

                $feature->geometry = $geometryResult->geometry;
                $feature->geometry_type = $geometryType;
                $feature->srid = $srid;
                $feature->save();
            }

            $feature->load('datasetRecord:id,values,identifier_value');
            return response()->json($feature->toGeoJsonFeature());
        });
    }

    public function query(Request $request, Dataset $dataset): JsonResponse
    {
        if (!$dataset->isSpatial()) {
            return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        }

        $validated = $request->validate([
            'field' => ['required', 'string', 'max:100'],
            'operator' => ['sometimes', 'in:equals,contains'],
            'value' => ['required', 'string', 'max:500'],
        ]);

        $field = $dataset->fields()->where('name', $validated['field'])->first();
        if (!$field) {
            return response()->json(['message' => 'The selected field does not belong to this dataset.'], 422);
        }

        $operator = $validated['operator'] ?? 'contains';
        $value = $validated['value'];
        $query = GisFeature::where('gis_features.dataset_id', $dataset->id)
            ->with(['datasetRecord:id,values,identifier_value']);

        if ($operator === 'equals') {
            $query->whereHas('datasetRecord', function ($records) use ($field, $value) {
                $records->whereRaw("values->>? = ?", [$field->name, $value]);
            });
        } else {
            $query->whereHas('datasetRecord', function ($records) use ($field, $value) {
                $records->whereRaw("values->>? ILIKE ?", [$field->name, '%' . $value . '%']);
            });
        }

        $features = $query->orderByDesc('gis_features.created_at')->limit(500)->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features->map(fn ($feature) => $feature->toGeoJsonFeature())->values(),
            'meta' => ['total' => $features->count(), 'field' => $field->name, 'operator' => $operator, 'value' => $value],
        ]);
    }

    public function nearest(Request $request, Dataset $dataset): JsonResponse
    {
        if (!$dataset->isSpatial()) {
            return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        }

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'numeric', 'min:1', 'max:1000000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radius = isset($validated['radius']) ? (float) $validated['radius'] : null;
        $limit = (int) ($validated['limit'] ?? 1);
        $datasetSrid = (int) ($dataset->srid ?? 4326);

        $distanceExpression = $datasetSrid === 4326
            ? 'ST_Distance(gis_features.geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)'
            : 'ST_Distance(ST_Transform(gis_features.geometry, 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)';

        $query = GisFeature::where('gis_features.dataset_id', $dataset->id)
            ->with(['datasetRecord:id,values,identifier_value'])
            ->select('gis_features.*')
            ->selectRaw("{$distanceExpression} as distance_m", [$lng, $lat]);

        if ($radius !== null) {
            if ($datasetSrid === 4326) {
                $query->whereRaw('ST_DWithin(gis_features.geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radius]);
            } else {
                $query->whereRaw('ST_DWithin(ST_Transform(gis_features.geometry, 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radius]);
            }
        }

        $features = $query->orderBy('distance_m')->limit($limit)->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features->map(function ($feature) {
                $geojson = $feature->toGeoJsonFeature();
                $geojson['distance_m'] = round((float) $feature->distance_m, 2);
                return $geojson;
            })->values(),
            'meta' => ['total' => $features->count(), 'lat' => $lat, 'lng' => $lng, 'radius' => $radius],
        ]);
    }

    public function measure(Request $request, Dataset $dataset): JsonResponse
    {
        if (!$dataset->isSpatial()) {
            return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        }

        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'string', 'in:LineString,MultiLineString,Polygon,MultiPolygon'],
            'geometry.coordinates' => ['required', 'array'],
        ]);

        $geojson = json_encode([
            'type' => $validated['geometry']['type'],
            'coordinates' => $validated['geometry']['coordinates'],
        ], JSON_THROW_ON_ERROR);

        $geometryType = $validated['geometry']['type'];
        $isArea = in_array($geometryType, ['Polygon', 'MultiPolygon'], true);

        $sql = $isArea
            ? 'SELECT ST_Area(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) as value_meters'
            : 'SELECT ST_Length(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) as value_meters';

        $result = DB::selectOne($sql, [$geojson]);
        $value = (float) ($result->value_meters ?? 0);

        if ($value <= 0) {
            return response()->json(['message' => 'تعذر حساب قياس هندسي صالح لهذه الرسمة.'], 422);
        }

        return response()->json([
            'geometry_type' => $geometryType,
            'measurement_type' => $isArea ? 'area' : 'distance',
            'meters' => $isArea ? null : round($value, 3),
            'kilometers' => $isArea ? null : round($value / 1000, 6),
            'square_meters' => $isArea ? round($value, 3) : null,
            'square_kilometers' => $isArea ? round($value / 1000000, 6) : null,
            'dunums' => $isArea ? round($value / 1000, 6) : null,
        ]);
    }

    public function buffer(Request $request, Dataset $dataset): JsonResponse
    {
        if (!$dataset->isSpatial()) {
            return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        }

        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'string', 'in:Point,LineString,Polygon,MultiPoint,MultiLineString,MultiPolygon'],
            'geometry.coordinates' => ['required', 'array'],
            'distance_m' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ]);

        $geojson = json_encode([
            'type' => $validated['geometry']['type'],
            'coordinates' => $validated['geometry']['coordinates'],
        ]);

        $result = DB::selectOne(
            'SELECT ST_AsGeoJSON(ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography, ?)) as geojson',
            [$geojson, (float) $validated['distance_m']]
        );

        if (!$result?->geojson) {
            return response()->json(['message' => 'تعذر إنشاء نطاق Buffer.'], 422);
        }

        return response()->json([
            'type' => 'Feature',
            'geometry' => json_decode($result->geojson, true),
            'properties' => [
                'distance_m' => (float) $validated['distance_m'],
                'source_dataset_id' => $dataset->id,
            ],
        ]);
    }

    public function destroy(Dataset $dataset, GisFeature $feature): JsonResponse
    {
        $this->ensureFeatureBelongsToDataset($dataset, $feature);
        $this->ensureWebEditableDataset($dataset);
        $feature->delete();
        return response()->json(['message' => 'GIS feature deleted successfully.']);
    }

    private function applyDefaults(Dataset $dataset, array $values): array
    {
        foreach ($dataset->fields as $field) {
            if (!array_key_exists($field->name, $values) && $field->default_value !== null) {
                $values[$field->name] = $field->default_value;
            }
        }

        return $values;
    }

    private function validateChildReferences(Dataset $dataset, array $values): void
    {
        $relationships = \App\Models\DatasetRelationship::where('child_dataset_id', $dataset->id)
            ->with(['parentDataset', 'parentField', 'childField'])
            ->get();

        foreach ($relationships as $relationship) {
            $childField = $relationship->childField;
            $parentField = $relationship->parentField;
            $childValue = $values[$childField->name] ?? null;

            if ($childValue === null) {
                if (!$relationship->is_nullable && $childField->is_required) {
                    throw new \RuntimeException("Child field '{$childField->name}' is required and cannot be null for this relationship.");
                }
                continue;
            }

            $parentExists = DatasetRecord::where('dataset_id', $relationship->parent_dataset_id)
                ->whereJsonContains('values', [$parentField->name => $childValue])
                ->exists();

            if (!$parentExists) {
                throw new \RuntimeException("Referenced parent record not found for field '{$childField->name}' with value: {$childValue}.");
            }
        }
    }

    private function ensureWebEditableDataset(Dataset $dataset): void
    {
        abort_unless($dataset->isWebEditable(), 403, 'GIS features can only be modified for web-editable datasets.');
    }

    private function ensureFeatureBelongsToDataset(Dataset $dataset, GisFeature $feature): void
    {
        abort_unless($feature->dataset_id === $dataset->id, 404);
    }
}
