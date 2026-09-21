<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\GisFeature;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OperationalGisService
{
    public function nearestAssets(float $latitude, float $longitude, int $limit = 5): array
    {
        $limit = min(max($limit, 1), 20);

        $rows = DB::select(
            "SELECT
                gf.id,
                gf.dataset_id,
                gf.dataset_record_id,
                d.display_name AS dataset_name,
                d.geometry_type,
                d.srid,
                dr.identifier_value,
                dr.values AS attributes,
                ST_Distance(
                    ST_Transform(gf.geometry, 4326)::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) AS distance_m
             FROM gis_features gf
             INNER JOIN datasets d ON d.id = gf.dataset_id
             LEFT JOIN dataset_records dr ON dr.id = gf.dataset_record_id
             WHERE d.is_spatial = true
               AND d.is_active = true
               AND d.management_mode IN ('official', 'operational')
               AND gf.geometry IS NOT NULL
               AND NOT ST_IsEmpty(gf.geometry)
               AND ST_IsValid(gf.geometry)
             ORDER BY ST_Distance(
                ST_Transform(gf.geometry, 4326)::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
             )
             LIMIT ?",
            [$longitude, $latitude, $longitude, $latitude, $limit]
        );

        return array_map(fn ($row) => [
            'id' => (int) $row->id,
            'dataset_id' => (int) $row->dataset_id,
            'dataset_record_id' => $row->dataset_record_id !== null ? (int) $row->dataset_record_id : null,
            'dataset_name' => $row->dataset_name,
            'geometry_type' => $row->geometry_type,
            'srid' => (int) $row->srid,
            'identifier' => $row->identifier_value,
            'attributes' => is_array($row->attributes) ? $row->attributes : (json_decode($row->attributes ?? '[]', true) ?: []),
            'distance_m' => round((float) $row->distance_m, 2),
        ], $rows);
    }

    public function complaintContext(Complaint $complaint): array
    {
        return [
            'linked' => $this->formatFeatures($complaint->gisFeatures()->with(['datasetRecord'])->get()),
            'nearest' => ($complaint->latitude !== null && $complaint->longitude !== null)
                ? $this->nearestAssets((float) $complaint->latitude, (float) $complaint->longitude)
                : [],
        ];
    }

    public function workOrderContext(WorkOrder $workOrder): array
    {
        $latitude = $workOrder->latitude;
        $longitude = $workOrder->longitude;

        if ($latitude === null || $longitude === null) {
            $fallback = $workOrder->complaints()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('created_at')
                ->first(['latitude', 'longitude']);
            $latitude = $fallback?->latitude;
            $longitude = $fallback?->longitude;
        }

        return [
            'linked' => $this->formatFeatures($workOrder->gisFeatures()->with(['datasetRecord'])->get()),
            'nearest' => ($latitude !== null && $longitude !== null)
                ? $this->nearestAssets((float) $latitude, (float) $longitude)
                : [],
            'latitude' => $latitude !== null ? (float) $latitude : null,
            'longitude' => $longitude !== null ? (float) $longitude : null,
        ];
    }

    public function linkComplaint(Complaint $complaint, GisFeature $feature, ?int $userId = null): void
    {
        $this->ensureLinkableFeature($feature);
        $complaint->gisFeatures()->syncWithoutDetaching([
            $feature->id => ['created_by' => $userId],
        ]);
    }

    public function unlinkComplaint(Complaint $complaint, GisFeature $feature): void
    {
        $complaint->gisFeatures()->detach($feature->id);
    }

    public function linkWorkOrder(WorkOrder $workOrder, GisFeature $feature, ?int $userId = null): void
    {
        $this->ensureLinkableFeature($feature);
        $workOrder->gisFeatures()->syncWithoutDetaching([
            $feature->id => ['created_by' => $userId],
        ]);
    }

    public function unlinkWorkOrder(WorkOrder $workOrder, GisFeature $feature): void
    {
        $workOrder->gisFeatures()->detach($feature->id);
    }

    private function ensureLinkableFeature(GisFeature $feature): void
    {
        $dataset = $feature->dataset;
        if (!$dataset || !$dataset->is_spatial || !$dataset->is_active) {
            throw new InvalidArgumentException('The GIS feature belongs to an inactive or non-spatial dataset.');
        }
    }

    private function formatFeatures($features): array
    {
        return $features->map(fn (GisFeature $feature) => [
            'id' => $feature->id,
            'dataset_id' => $feature->dataset_id,
            'dataset_name' => $feature->dataset?->display_name,
            'geometry_type' => $feature->geometry_type,
            'srid' => (int) $feature->srid,
            'identifier' => $feature->datasetRecord?->identifier_value,
            'attributes' => $feature->datasetRecord?->values ?? [],
        ])->values()->all();
    }
}
