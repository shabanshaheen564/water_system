<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetInspection extends Model
{
    protected $fillable = [
        'gis_feature_id',
        'inspected_by',
        'inspection_at',
        'result',
        'problem_description',
        'notes',
    ];

    protected function casts(): array
    {
        return ['inspection_at' => 'datetime'];
    }

    public function gisFeature(): BelongsTo
    {
        return $this->belongsTo(GisFeature::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
