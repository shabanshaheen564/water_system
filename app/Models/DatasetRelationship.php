<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetRelationship extends Model
{
    protected $fillable = [
        'parent_dataset_id',
        'child_dataset_id',
        'parent_field_id',
        'child_field_id',
        'relationship_type',
    ];

    public function parentDataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class, 'parent_dataset_id');
    }

    public function childDataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class, 'child_dataset_id');
    }

    public function parentField(): BelongsTo
    {
        return $this->belongsTo(DatasetField::class, 'parent_field_id');
    }

    public function childField(): BelongsTo
    {
        return $this->belongsTo(DatasetField::class, 'child_field_id');
    }
}