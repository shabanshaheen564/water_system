<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class GeometryCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // Return raw WKB/WKT from database
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // If it's already a string (WKB), return as is
        if (is_string($value)) {
            return $value;
        }
        
        // If it's an array or object, convert to JSON string
        return json_encode($value);
    }
}