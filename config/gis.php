<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GIS Coordinate Reference Systems
    |--------------------------------------------------------------------------
    |
    | Spatial data may be stored in its source/projected CRS per dataset.
    | GeoJSON sent to the web map is always transformed to WGS84 (EPSG:4326).
    |
    */
    'output_srid' => (int) env('GIS_OUTPUT_SRID', 4326),

    /*
    | EPSG:28191 = Palestine 1923 / Palestine Grid.
    | EPSG:4326  = WGS 84.
    | EPSG:3857  = Web Mercator.
    */
    'common_srids' => [
        4326 => 'WGS 84',
        3857 => 'WGS 84 / Pseudo-Mercator',
        28191 => 'Palestine 1923 / Palestine Grid',
    ],

    'geometry_types' => [
        'Point',
        'MultiPoint',
        'LineString',
        'MultiLineString',
        'Polygon',
        'MultiPolygon',
    ],
];
