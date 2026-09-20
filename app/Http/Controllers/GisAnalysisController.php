<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\GisFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GisAnalysisController extends Controller
{
    public function analyze(Request $request, Dataset $dataset): JsonResponse
    {
        if (!$dataset->isSpatial()) {
            return response()->json(['message' => 'This dataset is not configured as spatial.'], 422);
        }

        $validated = $request->validate([
            'operation' => ['required', 'string', 'in:intersection,within,contains,service_area,affected_area,density,risk_zone'],
            'target_dataset_id' => ['nullable', 'integer', 'exists:datasets,id'],
            'distance_m' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'cell_size_m' => ['nullable', 'numeric', 'min:10', 'max:10000'],
            'min_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $operation = $validated['operation'];
        $limit = 500;

        if (in_array($operation, ['intersection', 'within', 'contains', 'affected_area'], true)) {
            $target = Dataset::find($validated['target_dataset_id'] ?? null);
            if (!$target || !$target->isSpatial()) {
                return response()->json(['message' => 'اختر طبقة مكانية مستهدفة للتحليل.'], 422);
            }
            if ($target->id === $dataset->id) {
                return response()->json(['message' => 'يجب اختيار طبقة مختلفة للتحليل.'], 422);
            }
        }

        if (in_array($operation, ['service_area', 'affected_area'], true) && empty($validated['distance_m'])) {
            return response()->json(['message' => 'أدخل مسافة التحليل بالمتر.'], 422);
        }

        if (in_array($operation, ['density', 'risk_zone'], true) && empty($validated['cell_size_m'])) {
            $validated['cell_size_m'] = 250;
        }

        if ($operation === 'risk_zone' && empty($validated['min_count'])) {
            $validated['min_count'] = 2;
        }

        return match ($operation) {
            'intersection', 'within', 'contains' => $this->overlay($dataset, $target, $operation, $limit),
            'service_area' => $this->serviceArea($dataset, (float) $validated['distance_m']),
            'affected_area' => $this->affectedArea($dataset, $target, (float) $validated['distance_m']),
            'density', 'risk_zone' => $this->density($dataset, (float) $validated['cell_size_m'], (int) ($validated['min_count'] ?? 1), $operation === 'risk_zone', $limit),
        };
    }

    private function overlay(Dataset $source, Dataset $target, string $operation, int $limit): JsonResponse
    {
        $predicate = match ($operation) {
            'within' => 'ST_Within(a.geom_4326, b.geom_4326)',
            'contains' => 'ST_Within(b.geom_4326, a.geom_4326)',
            default => 'ST_Intersects(a.geom_4326, b.geom_4326)',
        };

        $outputGeometry = $operation === 'intersection'
            ? 'ST_Intersection(a.geom_4326, b.geom_4326)'
            : 'a.geom_4326';

        $rows = DB::select(
            "WITH source AS (
                SELECT id, dataset_record_id, ST_Transform(geometry, 4326) AS geom_4326
                FROM gis_features WHERE dataset_id = ?
            ),
            target AS (
                SELECT id, dataset_record_id, ST_Transform(geometry, 4326) AS geom_4326
                FROM gis_features WHERE dataset_id = ?
            )
            SELECT a.id AS source_feature_id, b.id AS target_feature_id,
                   ST_AsGeoJSON({$outputGeometry}) AS geojson
            FROM source a
            JOIN target b ON {$predicate}
            WHERE NOT ST_IsEmpty({$outputGeometry})
            LIMIT {$limit}",
            [$source->id, $target->id]
        );

        $features = collect($rows)->map(function ($row) use ($operation, $source, $target) {
            return [
                'type' => 'Feature',
                'geometry' => json_decode($row->geojson, true),
                'properties' => [
                    'analysis' => $operation,
                    'source_dataset_id' => $source->id,
                    'source_feature_id' => (int) $row->source_feature_id,
                    'target_dataset_id' => $target->id,
                    'target_feature_id' => (int) $row->target_feature_id,
                ],
            ];
        })->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => ['operation' => $operation, 'total' => $features->count()],
        ]);
    }

    private function serviceArea(Dataset $dataset, float $distance): JsonResponse
    {
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(ST_Union(ST_Buffer(ST_Transform(geometry, 4326)::geography, ?)::geometry)) AS geojson
             FROM gis_features WHERE dataset_id = ?',
            [$distance, $dataset->id]
        );

        return $this->singleGeometryResponse($row?->geojson, [
            'analysis' => 'service_area',
            'source_dataset_id' => $dataset->id,
            'distance_m' => $distance,
        ]);
    }

    private function affectedArea(Dataset $source, Dataset $target, float $distance): JsonResponse
    {
        $row = DB::selectOne(
            "WITH source_buffer AS (
                SELECT ST_Union(ST_Buffer(ST_Transform(geometry, 4326)::geography, ?)::geometry) AS geom
                FROM gis_features WHERE dataset_id = ?
            ),
            target_union AS (
                SELECT ST_Union(ST_Transform(geometry, 4326)) AS geom
                FROM gis_features WHERE dataset_id = ?
            )
            SELECT ST_AsGeoJSON(ST_Intersection(source_buffer.geom, target_union.geom)) AS geojson
            FROM source_buffer, target_union
            WHERE source_buffer.geom IS NOT NULL AND target_union.geom IS NOT NULL",
            [$distance, $source->id, $target->id]
        );

        return $this->singleGeometryResponse($row?->geojson, [
            'analysis' => 'affected_area',
            'source_dataset_id' => $source->id,
            'target_dataset_id' => $target->id,
            'distance_m' => $distance,
        ]);
    }

    private function density(Dataset $dataset, float $cellSize, int $minCount, bool $riskOnly, int $limit): JsonResponse
    {
        $rows = DB::select(
            "WITH points AS (
                SELECT ST_Transform(
                    CASE
                        WHEN ST_GeometryType(geometry) = 'ST_Point' THEN geometry
                        ELSE ST_PointOnSurface(geometry)
                    END, 3857
                ) AS geom
                FROM gis_features WHERE dataset_id = ?
            ),
            cells AS (
                SELECT ST_SnapToGrid(geom, ?) AS cell, COUNT(*)::integer AS feature_count
                FROM points
                GROUP BY ST_SnapToGrid(geom, ?)
            )
            SELECT ST_AsGeoJSON(
                       ST_Transform(
                           ST_Envelope(ST_Collect(
                               ST_Transform(cell, 4326)
                           )), 4326
                       )
                   ) AS geojson,
                   feature_count,
                   ST_AsGeoJSON(ST_Transform(ST_Centroid(cell), 4326)) AS center_geojson
            FROM cells
            WHERE feature_count >= ?
            LIMIT {$limit}",
            [$dataset->id, $cellSize, $cellSize, $minCount]
        );

        $features = collect($rows)->map(function ($row) use ($dataset, $cellSize, $riskOnly) {
            $center = json_decode($row->center_geojson, true);
            return [
                'type' => 'Feature',
                'geometry' => $center,
                'properties' => [
                    'analysis' => $riskOnly ? 'risk_zone' : 'density',
                    'source_dataset_id' => $dataset->id,
                    'feature_count' => (int) $row->feature_count,
                    'cell_size_m' => $cellSize,
                    'density_per_km2' => round(((int) $row->feature_count) / (($cellSize * $cellSize) / 1000000), 3),
                ],
            ];
        })->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => [
                'operation' => $riskOnly ? 'risk_zone' : 'density',
                'cell_size_m' => $cellSize,
                'min_count' => $minCount,
                'total' => $features->count(),
            ],
        ]);
    }

    private function singleGeometryResponse(?string $geojson, array $properties): JsonResponse
    {
        if (!$geojson) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'meta' => ['total' => 0],
            ]);
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'geometry' => json_decode($geojson, true),
                'properties' => $properties,
            ]],
            'meta' => ['total' => 1],
        ]);
    }
}
