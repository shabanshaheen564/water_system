<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE gis_features
                ALTER COLUMN geometry SET NOT NULL,
                ALTER COLUMN geometry_type SET NOT NULL,
                ALTER COLUMN srid SET NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE gis_features
            ADD CONSTRAINT gis_features_geometry_srid_check
            CHECK (ST_SRID(geometry) = srid)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE gis_features
            ADD CONSTRAINT gis_features_geometry_type_check
            CHECK (
                UPPER(geometry_type) = UPPER(
                    CASE
                        WHEN GeometryType(geometry) = 'POINT' THEN 'Point'
                        WHEN GeometryType(geometry) = 'MULTIPOINT' THEN 'MultiPoint'
                        WHEN GeometryType(geometry) = 'LINESTRING' THEN 'LineString'
                        WHEN GeometryType(geometry) = 'MULTILINESTRING' THEN 'MultiLineString'
                        WHEN GeometryType(geometry) = 'POLYGON' THEN 'Polygon'
                        WHEN GeometryType(geometry) = 'MULTIPOLYGON' THEN 'MultiPolygon'
                        ELSE GeometryType(geometry)
                    END
                )
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE gis_features
            ADD CONSTRAINT gis_features_geometry_not_empty_check
            CHECK (NOT ST_IsEmpty(geometry))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gis_features DROP CONSTRAINT IF EXISTS gis_features_geometry_not_empty_check');
        DB::statement('ALTER TABLE gis_features DROP CONSTRAINT IF EXISTS gis_features_geometry_type_check');
        DB::statement('ALTER TABLE gis_features DROP CONSTRAINT IF EXISTS gis_features_geometry_srid_check');

        DB::statement(<<<'SQL'
            ALTER TABLE gis_features
                ALTER COLUMN geometry DROP NOT NULL,
                ALTER COLUMN geometry_type DROP NOT NULL,
                ALTER COLUMN srid DROP NOT NULL
        SQL);
    }
};
