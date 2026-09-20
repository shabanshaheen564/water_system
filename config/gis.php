<?php

return [
    'output_srid' => (int) env('GIS_OUTPUT_SRID', 4326),

    /*
    | Explicit coordinate-operation overrides. Stored source geometry is never
    | modified. Other CRS pairs continue to use PROJ's automatic selection.
    */
    'output_transformations' => [
        28191 => [
            'target_srid' => 4326,
            'operation' => 'urn:ogc:def:coordinateOperation:EPSG::8650',
            'intermediate_srid' => 4281,
        ],
    ],

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
