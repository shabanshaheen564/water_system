<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetField extends Model
{
    protected $fillable = [
        'dataset_id',
        'name',
        'display_name',
        'data_type',
        'is_required',
        'is_unique',
        'is_identifier',
        'default_value',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_identifier' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}