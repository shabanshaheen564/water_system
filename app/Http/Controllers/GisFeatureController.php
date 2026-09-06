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
        if (!$dataset->isSpatial()) {
            return response()->json([
                'message' => 'This dataset is not configured as spatial.',
            ], 422);
        }

        $query = GisFeature::where('dataset_id', $dataset->id)
            ->with(['datasetRecord:id,values,identifier_value']);

        // Bounding box filter
        if ($request->has('bbox')) {
            $bbox = explode(',', $request->bbox);
            if (count($bbox) === 4) {
                [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $bbox);
                $envelope = "ST_MakeEnvelope($minLng, $minLat, $maxLng, $maxLat, 4326)";
                $query->whereRaw("geometry && $envelope");
            }
        }

        // Optional: point-radius filter
        if ($request->has(['lat', 'lng', 'radius'])) {
            $lat = $request->float('lat');
            $lng = $request->float('lng');
            $radius = $request->float('radius');
            $point = "ST_SetSRID(ST_MakePoint($lng, $lat), 4326)";
            $query->whereRaw("ST_DWithin(geometry, $point, $radius)");
        }

        $features = $query->orderBy('created_at', 'desc')->paginate();

        $data = $features->getCollection()->map(function ($feature) {
            return $feature->toGeoJsonFeature();
        });

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $data,
            'links' => [
                'first' => $features->url(1),
                'last' => $features->url($features->lastPage()),
                'prev' => $features->previousPageUrl(),
                'next' => $features->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $features->currentPage(),
                'from' => $features->firstItem(),
                'last_page' => $features->lastPage(),
                'path' => $features->path(),
                'per_page' => $features->perPage(),
                'to' => $features->lastItem(),
                'total' => $features->total(),
            ],
        ]);
    }

    public function store(StoreGisFeatureRequest $request, Dataset $dataset): JsonResponse
    {
        if (!$dataset->isSpatial()) {
            return response()->json([
                'message' => 'This dataset is not configured as spatial.',
            ], 422);
        }

        $validated = $request->validated();
        $datasetRecordId = $validated['dataset_record_id'];

        // Verify the record belongs to this dataset
        $record = DatasetRecord::where('id', $datasetRecordId)
            ->where('dataset_id', $dataset->id)
            ->firstOrFail();

        // Check if feature already exists for this record
        $existingFeature = GisFeature::where('dataset_record_id', $datasetRecordId)->first();
        if ($existingFeature) {
            return response()->json([
                'message' => 'A GIS feature already exists for this record.',
            ], 422);
        }

        return DB::transaction(function () use ($validated, $dataset, $datasetRecordId, $request) {
            $geometryType = $validated['geometry']['type'];
            $coordinates = $validated['geometry']['coordinates'];

            // Build GeoJSON for PostGIS
            $geojson = json_encode([
                'type' => $geometryType,
                'coordinates' => $coordinates,
            ]);

            // Use dataset SRID or default to 4326
            $srid = $dataset->srid ?? 4326;

            // Create geometry using PostGIS functions
            $geometry = DB::selectOne(
                "SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?) as geometry",
                [$geojson, $srid]
            )->geometry;

            $feature = GisFeature::create([
                'dataset_record_id' => $datasetRecordId,
                'dataset_id' => $dataset->id,
                'geometry' => $geometry,
                'geometry_type' => $validated['geometry']['type'],
                'srid' => $srid,
            ]);

            $feature->load('datasetRecord:id,values,identifier_value');

            return response()->json($feature->toGeoJsonFeature(), 201);
        });
    }

    public function show(Dataset $dataset, GisFeature $feature): JsonResponse
    {
        if ($feature->dataset_id !== $dataset->id) {
            return response()->json([
                'message' => 'Feature not found in this dataset.',
            ], 404);
        }

        $feature->load('datasetRecord:id,values,identifier_value');

        return response()->json($feature->toGeoJsonFeature());
    }

    public function update(UpdateGisFeatureRequest $request, Dataset $dataset, GisFeature $feature): JsonResponse
    {
        if ($feature->dataset_id !== $dataset->id) {
            return response()->json([
                'message' => 'Feature not found in this dataset.',
            ], 404);
        }

        if (!$dataset->isSpatial()) {
            return response()->json([
                'message' => 'This dataset is not configured as spatial.',
            ], 422);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $feature, $dataset) {
            if (isset($validated['geometry'])) {
                $geometryType = $validated['geometry']['type'];
                $coordinates = $validated['geometry']['coordinates'];

                $geojson = json_encode([
                    'type' => $geometryType,
                    'coordinates' => $coordinates,
                ]);

                $srid = $dataset->srid ?? 4326;

                $geometry = DB::selectOne(
                    "SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?) as geometry",
                    [$geojson, $srid]
                )->geometry;

                $feature->geometry = $geometry;
                $feature->geometry_type = $geometryType;
                $feature->srid = $srid;
            }

            $feature->save();
            $feature->load('datasetRecord:id,values,identifier_value');

            return response()->json($feature->toGeoJsonFeature());
        });
    }
}