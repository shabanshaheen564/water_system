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
        $datasetSrid = $dataset->srid ?? 4326;
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
            if ($datasetSrid == 4326) {
                $query->whereRaw('ST_DWithin(geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radius]);
            } else {
                $pointSql = 'ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), ?::integer)';
                $query->whereRaw("ST_DWithin(geometry, {$pointSql}, ?)", [$lng, $lat, $datasetSrid, $radius]);
            }
        }
        $features = $query->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 100), 1), 500));
        return response()->json(['type' => 'FeatureCollection', 'features' => $features->getCollection()->map(fn ($feature) => $feature->toGeoJsonFeature())->values(), 'links' => ['first' => $features->url(1), 'last' => $features->url($features->lastPage()), 'prev' => $features->previousPageUrl(), 'next' => $features->nextPageUrl()], 'meta' => ['current_page' => $features->currentPage(), 'from' => $features->firstItem(), 'last_page' => $features->lastPage(), 'path' => $features->path(), 'per_page' => $features->perPage(), 'to' => $features->lastItem(), 'total' => $features->total()]]);
    }

    public function store(StoreGisFeatureRequest $request, Dataset $dataset): JsonResponse
    {
        if (!$dataset->isSpatial()) return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        $validated = $request->validated();
        $record = DatasetRecord::where('id', $validated['dataset_record_id'])->where('dataset_id', $dataset->id)->firstOrFail();
        if (GisFeature::where('dataset_record_id', $record->id)->exists()) return response()->json(['message' => 'A GIS feature already exists for this record.'], 422);
        return DB::transaction(function () use ($validated, $dataset, $record) {
            $geojson = json_encode(['type' => $validated['geometry']['type'], 'coordinates' => $validated['geometry']['coordinates']]);
            $srid = $dataset->srid ?? 4326;
            $geometry = DB::selectOne('SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?) as geometry', [$geojson, $srid])->geometry;
            $feature = GisFeature::create(['dataset_record_id' => $record->id, 'dataset_id' => $dataset->id, 'geometry' => $geometry, 'geometry_type' => $validated['geometry']['type'], 'srid' => $srid]);
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
        if (!$dataset->isSpatial()) return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        $validated = $request->validated();
        return DB::transaction(function () use ($validated, $feature, $dataset) {
            if (isset($validated['geometry'])) {
                $geometryType = $validated['geometry']['type'];
                if ($dataset->geometry_type && $geometryType !== $dataset->geometry_type) return response()->json(['message' => "Geometry type must be {$dataset->geometry_type} for this dataset."], 422);
                $geojson = json_encode(['type' => $geometryType, 'coordinates' => $validated['geometry']['coordinates']]);
                $srid = $dataset->srid ?? 4326;
                $feature->geometry = DB::selectOne('SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?) as geometry', [$geojson, $srid])->geometry;
                $feature->geometry_type = $geometryType; $feature->srid = $srid;
            }
            $feature->save(); $feature->load('datasetRecord:id,values,identifier_value');
            return response()->json($feature->toGeoJsonFeature());
        });
    }

    public function destroy(Dataset $dataset, GisFeature $feature): JsonResponse
    {
        $this->ensureFeatureBelongsToDataset($dataset, $feature);
        $feature->delete();
        return response()->json(['message' => 'GIS feature deleted successfully.']);
    }

    private function ensureFeatureBelongsToDataset(Dataset $dataset, GisFeature $feature): void
    {
        abort_unless($feature->dataset_id === $dataset->id, 404);
    }
}
