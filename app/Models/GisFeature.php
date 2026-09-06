<?php

namespace App\Models;

use App\Casts\GeometryCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GisFeature extends Model
{
    protected $fillable = [
        'dataset_record_id',
        'dataset_id',
        'geometry',
        'geometry_type',
        'srid',
    ];

    protected function casts(): array
    {
        return [
            'geometry' => GeometryCast::class,
        ];
    }

    public function datasetRecord(): BelongsTo
    {
        return $this->belongsTo(DatasetRecord::class, 'dataset_record_id');
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function toGeoJsonFeature(int $outputSrid = 4326): array
    {
        $record = $this->datasetRecord;
        $properties = $record ? $record->values : [];

        // Convert WKB to GeoJSON using PostGIS with SRID transformation
        $geojson = null;
        if ($this->geometry) {
            $storedSrid = (int) ($this->srid ?? 4326);
            $outSrid = (int) $outputSrid;
            
            if ($storedSrid === $outSrid) {
                // No transformation needed
                $result = \Illuminate\Support\Facades\DB::selectOne(
                    "SELECT ST_AsGeoJSON(?) as geojson",
                    [$this->geometry]
                );
            } else {
                // Transform to output SRID before converting to GeoJSON
                // Cast SRID parameter to integer in SQL
                $result = \Illuminate\Support\Facades\DB::selectOne(
                    "SELECT ST_AsGeoJSON(ST_Transform(?, ?::integer)) as geojson",
                    [$this->geometry, $outSrid]
                );
            }
            
            if ($result && $result->geojson) {
                $geojson = json_decode($result->geojson, true);
            }
        }

        return [
            'type' => 'Feature',
            'id' => $this->id,
            'geometry' => $geojson,
            'properties' => $properties,
        ];
    }

    public static function getSupportedGeometryTypes(): array
    {
        return ['Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'];
    }
}