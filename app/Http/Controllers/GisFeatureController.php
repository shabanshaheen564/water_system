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
        return DB::transaction(function () use ($validated, $feature, $dataset) {
            if (isset($validated['geometry'])) {
                $geometryType = $validated['geometry']['type'];
                if ($dataset->geometry_type && $geometryType !== $dataset->geometry_type) return response()->json(['message' => "Geometry type must be {$dataset->geometry_type} for this dataset."], 422);
                $geojson = json_encode(['type' => $geometryType, 'coordinates' => $validated['geometry']['coordinates']]);
                $srid = $dataset->srid ?? 4326;
                $feature->geometry = $geometryResult = DB::selectOne(
                    'SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?::integer) as geometry, ST_IsValid(ST_SetSRID(ST_GeomFromGeoJSON(?), ?::integer)) as is_valid',
                    [$geojson, $srid, $geojson, $srid]
                );

                if (!$geometryResult->is_valid) {
                    return response()->json(['message' => 'The supplied geometry is not valid.'], 422);
                }

                $feature->geometry = $geometryResult->geometry;
                $feature->geometry_type = $geometryType; $feature->srid = $srid;
            }
            $feature->save(); $feature->load('datasetRecord:id,values,identifier_value');
            return response()->json($feature->toGeoJsonFeature());
        });
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
